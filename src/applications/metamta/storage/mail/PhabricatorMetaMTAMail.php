<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * See #394445 for an explanation of why this thing even exists.
 */
final class PhabricatorMetaMTAMail extends PhabricatorMetaMTADAO {

  const STATUS_QUEUE = 'queued';
  const STATUS_SENT  = 'sent';
  const STATUS_FAIL  = 'fail';
  const STATUS_VOID  = 'void';

  const MAX_RETRIES   = 250;
  const RETRY_DELAY   = 5;

  protected $parameters;
  protected $status;
  protected $message;
  protected $retryCount;
  protected $nextRetry;
  protected $relatedPHID;

  public function __construct() {

    $this->status     = self::STATUS_QUEUE;
    $this->retryCount = 0;
    $this->nextRetry  = time();
    $this->parameters = array();

    parent::__construct();
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'parameters'  => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  protected function setParam($param, $value) {
    $this->parameters[$param] = $value;
    return $this;
  }

  protected function getParam($param) {
    return idx($this->parameters, $param);
  }

  /**
   * Set tags (@{class:MetaMTANotificationType} constants) which identify the
   * content of this mail in a general way. These tags are used to allow users
   * to opt out of receiving certain types of mail, like updates when a task's
   * projects change.
   *
   * @param list<const> List of @{class:MetaMTANotificationType} constants.
   * @return this
   */
  public function setMailTags(array $tags) {
    $this->setParam('mailtags', $tags);
    return $this;
  }

  /**
   * In Gmail, conversations will be broken if you reply to a thread and the
   * server sends back a response without referencing your Message-ID, even if
   * it references a Message-ID earlier in the thread. To avoid this, use the
   * parent email's message ID explicitly if it's available. This overwrites the
   * "In-Reply-To" and "References" headers we would otherwise generate. This
   * needs to be set whenever an action is triggered by an email message. See
   * T251 for more details.
   *
   * @param   string The "Message-ID" of the email which precedes this one.
   * @return  this
   */
  public function setParentMessageID($id) {
    $this->setParam('parent-message-id', $id);
    return $this;
  }

  public function getParentMessageID() {
    return $this->getParam('parent-message-id');
  }

  public function getSubject() {
    return $this->getParam('subject');
  }

  public function addTos(array $phids) {
    $phids = array_unique($phids);
    $this->setParam('to', $phids);
    return $this;
  }

  public function addCCs(array $phids) {
    $phids = array_unique($phids);
    $this->setParam('cc', $phids);
    return $this;
  }

  public function addHeader($name, $value) {
    $this->parameters['headers'][$name] = $value;
    return $this;
  }

  public function addAttachment(PhabricatorMetaMTAAttachment $attachment) {
    $this->parameters['attachments'][] = $attachment;
    return $this;
  }

  public function getAttachments() {
    return $this->getParam('attachments');
  }

  public function setAttachments(array $attachments) {
    $this->setParam('attachments', $attachments);
    return $this;
  }

  public function setFrom($from) {
    $this->setParam('from', $from);
    return $this;
  }

  public function setReplyTo($reply_to) {
    $this->setParam('reply-to', $reply_to);
    return $this;
  }

  public function setSubject($subject) {
    $this->setParam('subject', $subject);
    return $this;
  }

  public function setBody($body) {
    $this->setParam('body', $body);
    return $this;
  }

  public function getBody() {
    return $this->getParam('body');
  }

  public function setIsHTML($html) {
    $this->setParam('is-html', $html);
    return $this;
  }

  public function getSimulatedFailureCount() {
    return nonempty($this->getParam('simulated-failures'), 0);
  }

  public function setSimulatedFailureCount($count) {
    $this->setParam('simulated-failures', $count);
    return $this;
  }

  public function getWorkerTaskID() {
    return $this->getParam('worker-task');
  }

  public function setWorkerTaskID($id) {
    $this->setParam('worker-task', $id);
    return $this;
  }

  /**
   * Flag that this is an auto-generated bulk message and should have bulk
   * headers added to it if appropriate. Broadly, this means some flavor of
   * "Precedence: bulk" or similar, but is implementation and configuration
   * dependent.
   *
   * @param bool  True if the mail is automated bulk mail.
   * @return this
   */
  public function setIsBulk($is_bulk) {
    $this->setParam('is-bulk', $is_bulk);
    return $this;
  }

  /**
   * Use this method to set an ID used for message threading. MetaMTA will
   * set appropriate headers (Message-ID, In-Reply-To, References and
   * Thread-Index) based on the capabilities of the underlying mailer.
   *
   * @param string  Unique identifier, appropriate for use in a Message-ID,
   *                In-Reply-To or References headers.
   * @param bool    If true, indicates this is the first message in the thread.
   * @return this
   */
  public function setThreadID($thread_id, $is_first_message = false) {
    $this->setParam('thread-id', $thread_id);
    $this->setParam('is-first-message', $is_first_message);
    return $this;
  }

  /**
   * Save a newly created mail to the database and attempt to send it
   * immediately if the server is configured for immediate sends. When
   * applications generate new mail they should generally use this method to
   * deliver it. If the server doesn't use immediate sends, this has the same
   * effect as calling save(): the mail will eventually be delivered by the
   * MetaMTA daemon.
   *
   * @return this
   */
  public function saveAndSend() {
    $ret = null;

    if (PhabricatorEnv::getEnvConfig('metamta.send-immediately')) {
      $ret = $this->sendNow();
    } else {
      $ret = $this->save();
    }

    return $ret;
  }

  protected function didWriteData() {
    parent::didWriteData();

    if (!$this->getWorkerTaskID()) {
      $mailer_task = new PhabricatorWorkerTask();
      $mailer_task->setTaskClass('PhabricatorMetaMTAWorker');
      $mailer_task->setData($this->getID());
      $mailer_task->save();
      $this->setWorkerTaskID($mailer_task->getID());
      $this->save();
    }
  }


  public function buildDefaultMailer() {
    $class_name = PhabricatorEnv::getEnvConfig('metamta.mail-adapter');
    PhutilSymbolLoader::loadClass($class_name);
    return newv($class_name, array());
  }

  /**
   * Attempt to deliver an email immediately, in this process.
   *
   * @param bool  Try to deliver this email even if it has already been
   *              delivered or is in backoff after a failed delivery attempt.
   * @param PhabricatorMailImplementationAdapter Use a specific mail adapter,
   *              instead of the default.
   *
   * @return void
   */
  public function sendNow(
    $force_send = false,
    PhabricatorMailImplementationAdapter $mailer = null) {

    if ($mailer === null) {
      $mailer = $this->buildDefaultMailer();
    }

    if (!$force_send) {
      if ($this->getStatus() != self::STATUS_QUEUE) {
        throw new Exception("Trying to send an already-sent mail!");
      }

      if (time() < $this->getNextRetry()) {
        throw new Exception("Trying to send an email before next retry!");
      }
    }

    try {
      $parameters = $this->parameters;
      $phids = array();
      foreach ($parameters as $key => $value) {
        switch ($key) {
          case 'from':
          case 'to':
          case 'cc':
            if (!is_array($value)) {
              $value = array($value);
            }
            foreach (array_filter($value) as $phid) {
              $phids[] = $phid;
            }
            break;
        }
      }

      $handles = id(new PhabricatorObjectHandleData($phids))
        ->loadHandles();

      $exclude = array();

      $params = $this->parameters;
      $default = PhabricatorEnv::getEnvConfig('metamta.default-address');
      if (empty($params['from'])) {
        $mailer->setFrom($default);
      } else {
        $from = $params['from'];

        // If the user has set their preferences to not send them email about
        // things they do, exclude them from being on To or Cc.
        $from_user = id(new PhabricatorUser())->loadOneWhere(
          'phid = %s',
          $from);
        if ($from_user) {
          $pref_key = PhabricatorUserPreferences::PREFERENCE_NO_SELF_MAIL;
          $exclude_self = $from_user
            ->loadPreferences()
            ->getPreference($pref_key);
          if ($exclude_self) {
            $exclude[$from] = true;
          }
        }

        if (!PhabricatorEnv::getEnvConfig('metamta.can-send-as-user')) {
          $handle = $handles[$from];
          if (empty($params['reply-to'])) {
            $params['reply-to'] = $handle->getEmail();
            $params['reply-to-name'] = $handle->getFullName();
          }
          $mailer->setFrom(
            $default,
            $handle->getFullName());
          unset($params['from']);
        }
      }

      $is_first = idx($params, 'is-first-message');
      unset($params['is-first-message']);

      $is_threaded = (bool)idx($params, 'thread-id');

      $reply_to_name = idx($params, 'reply-to-name', '');
      unset($params['reply-to-name']);

      $add_cc = array();
      $add_to = array();

      foreach ($params as $key => $value) {
        switch ($key) {
          case 'from':
            $mailer->setFrom($handles[$value]->getEmail());
            break;
          case 'reply-to':
            $mailer->addReplyTo($value, $reply_to_name);
            break;
          case 'to':
            $emails = $this->getDeliverableEmailsFromHandles(
              $value,
              $handles,
              $exclude);
            if ($emails) {
              $add_to = $emails;
            }
            break;
          case 'cc':
            $emails = $this->getDeliverableEmailsFromHandles(
              $value,
              $handles,
              $exclude);
            if ($emails) {
              $add_cc = $emails;
            }
            break;
          case 'headers':
            foreach ($value as $header_key => $header_value) {
              // NOTE: If we have \n in a header, SES rejects the email.
              $header_value = str_replace("\n", " ", $header_value);

              $mailer->addHeader($header_key, $header_value);
            }
            break;
          case 'attachments':
            foreach ($value as $attachment) {
              $mailer->addAttachment(
                $attachment->getData(),
                $attachment->getFilename(),
                $attachment->getMimeType()
              );
            }
            break;
          case 'body':
            $mailer->setBody($value);
            break;
          case 'subject':
            if ($is_threaded) {
              $add_re = PhabricatorEnv::getEnvConfig('metamta.re-prefix');

              // If this message has a single recipient, respect their "Re:"
              // preference. Otherwise, use the global setting.

              $to = idx($params, 'to', array());
              $cc = idx($params, 'cc', array());
              if (count($to) == 1 && count($cc) == 0) {
                $user = id(new PhabricatorUser())->loadOneWhere(
                  'phid = %s',
                  head($to));
                if ($user) {
                  $prefs = $user->loadPreferences();
                  $pref_key = PhabricatorUserPreferences::PREFERENCE_RE_PREFIX;
                  $add_re = $prefs->getPreference($pref_key, $add_re);
                }
              }

              if ($add_re) {
                $value = 'Re: '.$value;
              }
            }

            $mailer->setSubject($value);
            break;
          case 'is-html':
            if ($value) {
              $mailer->setIsHTML(true);
            }
            break;
          case 'is-bulk':
            if ($value) {
              if (PhabricatorEnv::getEnvConfig('metamta.precedence-bulk')) {
                $mailer->addHeader('Precedence', 'bulk');
              }
            }
            break;
          case 'thread-id':
            if ($is_first && $mailer->supportsMessageIDHeader()) {
              $mailer->addHeader('Message-ID',  $value);
            } else {
              $in_reply_to = $value;
              $references = array($value);
              $parent_id = $this->getParentMessageID();
              if ($parent_id) {
                $in_reply_to = $parent_id;
                // By RFC 2822, the most immediate parent should appear last
                // in the "References" header, so this order is intentional.
                $references[] = $parent_id;
              }
              $references = implode(' ', $references);
              $mailer->addHeader('In-Reply-To', $in_reply_to);
              $mailer->addHeader('References',  $references);
            }
            $thread_index = $this->generateThreadIndex($value, $is_first);
            $mailer->addHeader('Thread-Index', $thread_index);
            break;
          case 'mailtags':
            // Handled below.
            break;
          default:
            // Just discard.
        }
      }

      $mailer->addHeader('X-Phabricator-Sent-This-Message', 'Yes');
      $mailer->addHeader('X-Mail-Transport-Agent', 'MetaMTA');

      // If the message has mailtags, filter out any recipients who don't want
      // to receive this type of mail.
      $mailtags = $this->getParam('mailtags');
      if ($mailtags && ($add_to || $add_cc)) {

        $tag_header = array();
        foreach ($mailtags as $mailtag) {
          $tag_header[] = '<'.$mailtag.'>';
        }
        $tag_header = implode(', ', $tag_header);
        $mailer->addHeader('X-Phabricator-Mail-Tags', $tag_header);

        $exclude = array();

        $all_recipients = array_merge(
          array_keys($add_to),
          array_keys($add_cc));

        $all_prefs = id(new PhabricatorUserPreferences())->loadAllWhere(
          'userPHID in (%Ls)',
          $all_recipients);
        $all_prefs = mpull($all_prefs, null, 'getUserPHID');

        foreach ($all_recipients as $recipient) {
          $prefs = idx($all_prefs, $recipient);
          if (!$prefs) {
            continue;
          }

          $user_mailtags = $prefs->getPreference(
            PhabricatorUserPreferences::PREFERENCE_MAILTAGS,
            array());

          // The user must have elected to receive mail for at least one
          // of the mailtags.
          $send = false;
          foreach ($mailtags as $tag) {
            if (idx($user_mailtags, $tag, true)) {
              $send = true;
              break;
            }
          }

          if (!$send) {
            $exclude[$recipient] = true;
          }
        }

        $add_to = array_diff_key($add_to, $exclude);
        $add_cc = array_diff_key($add_cc, $exclude);
      }


      if ($add_to) {
        $mailer->addTos($add_to);
        if ($add_cc) {
          $mailer->addCCs($add_cc);
        }
      } else if ($add_cc) {
        // If we have CC addresses but no "to" address, promote the CCs to
        // "to".
        $mailer->addTos($add_cc);
      } else {
        $this->setStatus(self::STATUS_VOID);
        $this->setMessage(
          "Message has no valid recipients: all To/CC are disabled or ".
          "configured not to receive this mail.");
        return $this->save();
      }

    } catch (Exception $ex) {
      $this->setStatus(self::STATUS_FAIL);
      $this->setMessage($ex->getMessage());
      return $this->save();
    }

    if ($this->getRetryCount() < $this->getSimulatedFailureCount()) {
      $ok = false;
      $error = 'Simulated failure.';
    } else {
      try {
        $ok = $mailer->send();
        $error = null;
      } catch (Exception $ex) {
        $ok = false;
        $error = $ex->getMessage()."\n".$ex->getTraceAsString();
      }
    }

    if (!$ok) {
      $this->setMessage($error);
      if ($this->getRetryCount() > self::MAX_RETRIES) {
        $this->setStatus(self::STATUS_FAIL);
      } else {
        $this->setRetryCount($this->getRetryCount() + 1);
        $next_retry = time() + ($this->getRetryCount() * self::RETRY_DELAY);
        $this->setNextRetry($next_retry);
      }
    } else {
      $this->setStatus(self::STATUS_SENT);
    }

    return $this->save();
  }

  public static function getReadableStatus($status_code) {
    static $readable = array(
      self::STATUS_QUEUE => "Queued for Delivery",
      self::STATUS_FAIL  => "Delivery Failed",
      self::STATUS_SENT  => "Sent",
      self::STATUS_VOID  => "Void",
    );
    $status_code = coalesce($status_code, '?');
    return idx($readable, $status_code, $status_code);
  }

  private function generateThreadIndex($seed, $is_first_mail) {
    // When threading, Outlook ignores the 'References' and 'In-Reply-To'
    // headers that most clients use. Instead, it uses a custom 'Thread-Index'
    // header. The format of this header is something like this (from
    // camel-exchange-folder.c in Evolution Exchange):

    /* A new post to a folder gets a 27-byte-long thread index. (The value
     * is apparently unique but meaningless.) Each reply to a post gets a
     * 32-byte-long thread index whose first 27 bytes are the same as the
     * parent's thread index. Each reply to any of those gets a
     * 37-byte-long thread index, etc. The Thread-Index header contains a
     * base64 representation of this value.
     */

    // The specific implementation uses a 27-byte header for the first email
    // a recipient receives, and a random 5-byte suffix (32 bytes total)
    // thereafter. This means that all the replies are (incorrectly) siblings,
    // but it would be very difficult to keep track of the entire tree and this
    // gets us reasonable client behavior.

    $base = substr(md5($seed), 0, 27);
    if (!$is_first_mail) {
      // Not totally sure, but it seems like outlook orders replies by
      // thread-index rather than timestamp, so to get these to show up in the
      // right order we use the time as the last 4 bytes.
      $base .= ' '.pack('N', time());
    }

    return base64_encode($base);
  }

  private function getDeliverableEmailsFromHandles(
    array $phids,
    array $handles,
    array $exclude) {

    $emails = array();
    foreach ($phids as $phid) {
      if ($handles[$phid]->isDisabled()) {
        continue;
      }
      if (!$handles[$phid]->isComplete()) {
        continue;
      }
      if (isset($exclude[$phid])) {
        continue;
      }
      $emails[$phid] = $handles[$phid]->getEmail();
    }

    return $emails;
  }

}
