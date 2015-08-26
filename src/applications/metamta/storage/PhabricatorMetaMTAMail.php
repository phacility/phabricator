<?php

/**
 * @task recipients   Managing Recipients
 */
final class PhabricatorMetaMTAMail
  extends PhabricatorMetaMTADAO
  implements PhabricatorPolicyInterface {

  const RETRY_DELAY   = 5;

  protected $actorPHID;
  protected $parameters = array();
  protected $status;
  protected $message;
  protected $relatedPHID;

  private $recipientExpansionMap;
  private $routingMap;

  public function __construct() {

    $this->status     = PhabricatorMailOutboundStatus::STATUS_QUEUE;
    $this->parameters = array('sensitive' => true);

    parent::__construct();
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'parameters'  => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'actorPHID' => 'phid?',
        'status' => 'text32',
        'relatedPHID' => 'phid?',

        // T6203/NULLABILITY
        // This should just be empty if there's no body.
        'message' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'status' => array(
          'columns' => array('status'),
        ),
        'key_actorPHID' => array(
          'columns' => array('actorPHID'),
        ),
        'relatedPHID' => array(
          'columns' => array('relatedPHID'),
        ),
        'key_created' => array(
          'columns' => array('dateCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorMetaMTAMailPHIDType::TYPECONST);
  }

  protected function setParam($param, $value) {
    $this->parameters[$param] = $value;
    return $this;
  }

  protected function getParam($param, $default = null) {
    // Some old mail was saved without parameters because no parameters were
    // set or encoding failed. Recover in these cases so we can perform
    // mail migrations, see T9251.
    if (!is_array($this->parameters)) {
      $this->parameters = array();
    }

    return idx($this->parameters, $param, $default);
  }

  /**
   * These tags are used to allow users to opt out of receiving certain types
   * of mail, like updates when a task's projects change.
   *
   * @param list<const>
   * @return this
   */
  public function setMailTags(array $tags) {
    $this->setParam('mailtags', array_unique($tags));
    return $this;
  }

  public function getMailTags() {
    return $this->getParam('mailtags', array());
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

  public function addRawTos(array $raw_email) {

    // Strip addresses down to bare emails, since the MailAdapter API currently
    // requires we pass it just the address (like `alincoln@logcabin.org`), not
    // a full string like `"Abraham Lincoln" <alincoln@logcabin.org>`.
    foreach ($raw_email as $key => $email) {
      $object = new PhutilEmailAddress($email);
      $raw_email[$key] = $object->getAddress();
    }

    $this->setParam('raw-to', $raw_email);
    return $this;
  }

  public function addCCs(array $phids) {
    $phids = array_unique($phids);
    $this->setParam('cc', $phids);
    return $this;
  }

  public function setExcludeMailRecipientPHIDs(array $exclude) {
    $this->setParam('exclude', $exclude);
    return $this;
  }

  private function getExcludeMailRecipientPHIDs() {
    return $this->getParam('exclude', array());
  }

  public function setForceHeraldMailRecipientPHIDs(array $force) {
    $this->setParam('herald-force-recipients', $force);
    return $this;
  }

  private function getForceHeraldMailRecipientPHIDs() {
    return $this->getParam('herald-force-recipients', array());
  }

  public function getTranslation(array $objects) {
    $default_translation = PhabricatorEnv::getEnvConfig('translation.provider');
    $return = null;
    $recipients = array_merge(
      idx($this->parameters, 'to', array()),
      idx($this->parameters, 'cc', array()));
    foreach (array_select_keys($objects, $recipients) as $object) {
      $translation = null;
      if ($object instanceof PhabricatorUser) {
        $translation = $object->getTranslation();
      }
      if (!$translation) {
        $translation = $default_translation;
      }
      if ($return && $translation != $return) {
        return $default_translation;
      }
      $return = $translation;
    }

    if (!$return) {
      $return = $default_translation;
    }

    return $return;
  }

  public function addPHIDHeaders($name, array $phids) {
    $phids = array_unique($phids);
    foreach ($phids as $phid) {
      $this->addHeader($name, '<'.$phid.'>');
    }
    return $this;
  }

  public function addHeader($name, $value) {
    $this->parameters['headers'][] = array($name, $value);
    return $this;
  }

  public function addAttachment(PhabricatorMetaMTAAttachment $attachment) {
    $this->parameters['attachments'][] = $attachment->toDictionary();
    return $this;
  }

  public function getAttachments() {
    $dicts = $this->getParam('attachments');

    $result = array();
    foreach ($dicts as $dict) {
      $result[] = PhabricatorMetaMTAAttachment::newFromDictionary($dict);
    }
    return $result;
  }

  public function setAttachments(array $attachments) {
    assert_instances_of($attachments, 'PhabricatorMetaMTAAttachment');
    $this->setParam('attachments', mpull($attachments, 'toDictionary'));
    return $this;
  }

  public function setFrom($from) {
    $this->setParam('from', $from);
    $this->setActorPHID($from);
    return $this;
  }

  public function getFrom() {
    return $this->getParam('from');
  }

  public function setRawFrom($raw_email, $raw_name) {
    $this->setParam('raw-from', array($raw_email, $raw_name));
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

  public function setSubjectPrefix($prefix) {
    $this->setParam('subject-prefix', $prefix);
    return $this;
  }

  public function setVarySubjectPrefix($prefix) {
    $this->setParam('vary-subject-prefix', $prefix);
    return $this;
  }

  public function setBody($body) {
    $this->setParam('body', $body);
    return $this;
  }

  public function setSensitiveContent($bool) {
    $this->setParam('sensitive', $bool);
    return $this;
  }

  public function hasSensitiveContent() {
    return $this->getParam('sensitive', true);
  }

  public function setHTMLBody($html) {
    $this->setParam('html-body', $html);
    return $this;
  }

  public function getBody() {
    return $this->getParam('body');
  }

  public function getHTMLBody() {
    return $this->getParam('html-body');
  }

  public function setIsErrorEmail($is_error) {
    $this->setParam('is-error', $is_error);
    return $this;
  }

  public function getIsErrorEmail() {
    return $this->getParam('is-error', false);
  }

  public function getToPHIDs() {
    return $this->getParam('to', array());
  }

  public function getRawToAddresses() {
    return $this->getParam('raw-to', array());
  }

  public function getCcPHIDs() {
    return $this->getParam('cc', array());
  }

  /**
   * Force delivery of a message, even if recipients have preferences which
   * would otherwise drop the message.
   *
   * This is primarily intended to let users who don't want any email still
   * receive things like password resets.
   *
   * @param bool  True to force delivery despite user preferences.
   * @return this
   */
  public function setForceDelivery($force) {
    $this->setParam('force', $force);
    return $this;
  }

  public function getForceDelivery() {
    return $this->getParam('force', false);
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
   * Save a newly created mail to the database. The mail will eventually be
   * delivered by the MetaMTA daemon.
   *
   * @return this
   */
  public function saveAndSend() {
    return $this->save();
  }

  public function save() {
    if ($this->getID()) {
      return parent::save();
    }

    // NOTE: When mail is sent from CLI scripts that run tasks in-process, we
    // may re-enter this method from within scheduleTask(). The implementation
    // is intended to avoid anything awkward if we end up reentering this
    // method.

    $this->openTransaction();
      // Save to generate a mail ID and PHID.
      $result = parent::save();

      // Write the recipient edges.
      $editor = new PhabricatorEdgeEditor();
      $edge_type = PhabricatorMetaMTAMailHasRecipientEdgeType::EDGECONST;
      $recipient_phids = array_merge(
        $this->getToPHIDs(),
        $this->getCcPHIDs());
      $expanded_phids = $this->expandRecipients($recipient_phids);
      $all_phids = array_unique(array_merge(
        $recipient_phids,
        $expanded_phids));
      foreach ($all_phids as $curr_phid) {
        $editor->addEdge($this->getPHID(), $edge_type, $curr_phid);
      }
      $editor->save();

      // Queue a task to send this mail.
      $mailer_task = PhabricatorWorker::scheduleTask(
        'PhabricatorMetaMTAWorker',
        $this->getID(),
        array(
          'priority' => PhabricatorWorker::PRIORITY_ALERTS,
        ));

    $this->saveTransaction();

    return $result;
  }

  public function buildDefaultMailer() {
    return PhabricatorEnv::newObjectFromConfig('metamta.mail-adapter');
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
      if ($this->getStatus() != PhabricatorMailOutboundStatus::STATUS_QUEUE) {
        throw new Exception(pht('Trying to send an already-sent mail!'));
      }
    }

    try {
      $headers = $this->generateHeaders();

      $params = $this->parameters;

      $actors = $this->loadAllActors();
      $deliverable_actors = $this->filterDeliverableActors($actors);

      $default_from = PhabricatorEnv::getEnvConfig('metamta.default-address');
      if (empty($params['from'])) {
        $mailer->setFrom($default_from);
      }

      $is_first = idx($params, 'is-first-message');
      unset($params['is-first-message']);

      $is_threaded = (bool)idx($params, 'thread-id');

      $reply_to_name = idx($params, 'reply-to-name', '');
      unset($params['reply-to-name']);

      $add_cc = array();
      $add_to = array();

      // Only try to use preferences if everything is multiplexed, so we
      // get consistent behavior.
      $use_prefs = self::shouldMultiplexAllMail();

      $prefs = null;
      if ($use_prefs) {

        // If multiplexing is enabled, some recipients will be in "Cc"
        // rather than "To". We'll move them to "To" later (or supply a
        // dummy "To") but need to look for the recipient in either the
        // "To" or "Cc" fields here.
        $target_phid = head(idx($params, 'to', array()));
        if (!$target_phid) {
          $target_phid = head(idx($params, 'cc', array()));
        }

        if ($target_phid) {
          $user = id(new PhabricatorUser())->loadOneWhere(
            'phid = %s',
            $target_phid);
          if ($user) {
            $prefs = $user->loadPreferences();
          }
        }
      }

      foreach ($params as $key => $value) {
        switch ($key) {
          case 'raw-from':
            list($from_email, $from_name) = $value;
            $mailer->setFrom($from_email, $from_name);
            break;
          case 'from':
            $from = $value;
            $actor_email = null;
            $actor_name = null;
            $actor = idx($actors, $from);
            if ($actor) {
              $actor_email = $actor->getEmailAddress();
              $actor_name = $actor->getName();
            }
            $can_send_as_user = $actor_email &&
              PhabricatorEnv::getEnvConfig('metamta.can-send-as-user');

            if ($can_send_as_user) {
              $mailer->setFrom($actor_email, $actor_name);
            } else {
              $from_email = coalesce($actor_email, $default_from);
              $from_name = coalesce($actor_name, pht('Phabricator'));

              if (empty($params['reply-to'])) {
                $params['reply-to'] = $from_email;
                $params['reply-to-name'] = $from_name;
              }

              $mailer->setFrom($default_from, $from_name);
            }
            break;
          case 'reply-to':
            $mailer->addReplyTo($value, $reply_to_name);
            break;
          case 'to':
            $to_phids = $this->expandRecipients($value);
            $to_actors = array_select_keys($deliverable_actors, $to_phids);
            $add_to = array_merge(
              $add_to,
              mpull($to_actors, 'getEmailAddress'));
            break;
          case 'raw-to':
            $add_to = array_merge($add_to, $value);
            break;
          case 'cc':
            $cc_phids = $this->expandRecipients($value);
            $cc_actors = array_select_keys($deliverable_actors, $cc_phids);
            $add_cc = array_merge(
              $add_cc,
              mpull($cc_actors, 'getEmailAddress'));
            break;
          case 'attachments':
            $value = $this->getAttachments();
            foreach ($value as $attachment) {
              $mailer->addAttachment(
                $attachment->getData(),
                $attachment->getFilename(),
                $attachment->getMimeType());
            }
            break;
          case 'subject':
            $subject = array();

            if ($is_threaded) {
              $add_re = PhabricatorEnv::getEnvConfig('metamta.re-prefix');

              if ($prefs) {
                $add_re = $prefs->getPreference(
                  PhabricatorUserPreferences::PREFERENCE_RE_PREFIX,
                  $add_re);
              }

              if ($add_re) {
                $subject[] = 'Re:';
              }
            }

            $subject[] = trim(idx($params, 'subject-prefix'));

            $vary_prefix = idx($params, 'vary-subject-prefix');
            if ($vary_prefix != '') {
              $use_subject = PhabricatorEnv::getEnvConfig(
                'metamta.vary-subjects');

              if ($prefs) {
                $use_subject = $prefs->getPreference(
                  PhabricatorUserPreferences::PREFERENCE_VARY_SUBJECT,
                  $use_subject);
              }

              if ($use_subject) {
                $subject[] = $vary_prefix;
              }
            }

            $subject[] = $value;

            $mailer->setSubject(implode(' ', array_filter($subject)));
            break;
          case 'thread-id':

            // NOTE: Gmail freaks out about In-Reply-To and References which
            // aren't in the form "<string@domain.tld>"; this is also required
            // by RFC 2822, although some clients are more liberal in what they
            // accept.
            $domain = PhabricatorEnv::getEnvConfig('metamta.domain');
            $value = '<'.$value.'@'.$domain.'>';

            if ($is_first && $mailer->supportsMessageIDHeader()) {
              $headers[] = array('Message-ID',  $value);
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
              $headers[] = array('In-Reply-To', $in_reply_to);
              $headers[] = array('References',  $references);
            }
            $thread_index = $this->generateThreadIndex($value, $is_first);
            $headers[] = array('Thread-Index', $thread_index);
            break;
          default:
            // Other parameters are handled elsewhere or are not relevant to
            // constructing the message.
            break;
        }
      }

      $body = idx($params, 'body', '');
      $max = PhabricatorEnv::getEnvConfig('metamta.email-body-limit');
      if (strlen($body) > $max) {
        $body = id(new PhutilUTF8StringTruncator())
          ->setMaximumBytes($max)
          ->truncateString($body);
        $body .= "\n";
        $body .= pht('(This email was truncated at %d bytes.)', $max);
      }
      $mailer->setBody($body);

      $html_emails = false;
      if ($use_prefs && $prefs) {
        $html_emails = $prefs->getPreference(
          PhabricatorUserPreferences::PREFERENCE_HTML_EMAILS,
          $html_emails);
      }

      if ($html_emails && isset($params['html-body'])) {
        $mailer->setHTMLBody($params['html-body']);
      }

      // Pass the headers to the mailer, then save the state so we can show
      // them in the web UI.
      foreach ($headers as $header) {
        list($header_key, $header_value) = $header;
        $mailer->addHeader($header_key, $header_value);
      }
      $this->setParam('headers.sent', $headers);

      // Save the final deliverability outcomes and reasoning so we can
      // explain why things happened the way they did.
      $actor_list = array();
      foreach ($actors as $actor) {
        $actor_list[$actor->getPHID()] = array(
          'deliverable' => $actor->isDeliverable(),
          'reasons' => $actor->getDeliverabilityReasons(),
        );
      }
      $this->setParam('actors.sent', $actor_list);

      $this->setParam('routing.sent', $this->getParam('routing'));
      $this->setParam('routingmap.sent', $this->getRoutingRuleMap());

      if (!$add_to && !$add_cc) {
        $this->setStatus(PhabricatorMailOutboundStatus::STATUS_VOID);
        $this->setMessage(
          pht(
            'Message has no valid recipients: all To/Cc are disabled, '.
            'invalid, or configured not to receive this mail.'));
        return $this->save();
      }

      if ($this->getIsErrorEmail()) {
        $all_recipients = array_merge($add_to, $add_cc);
        if ($this->shouldRateLimitMail($all_recipients)) {
          $this->setStatus(PhabricatorMailOutboundStatus::STATUS_VOID);
          $this->setMessage(
            pht(
              'This is an error email, but one or more recipients have '.
              'exceeded the error email rate limit. Declining to deliver '.
              'message.'));
          return $this->save();
        }
      }

      if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
        $this->setStatus(PhabricatorMailOutboundStatus::STATUS_VOID);
        $this->setMessage(
          pht(
            'Phabricator is running in silent mode. See `%s` '.
            'in the configuration to change this setting.',
            'phabricator.silent'));
        return $this->save();
      }

      // Some mailers require a valid "To:" in order to deliver mail. If we
      // don't have any "To:", try to fill it in with a placeholder "To:".
      // If that also fails, move the "Cc:" line to "To:".
      if (!$add_to) {
        $placeholder_key = 'metamta.placeholder-to-recipient';
        $placeholder = PhabricatorEnv::getEnvConfig($placeholder_key);
        if ($placeholder !== null) {
          $add_to = array($placeholder);
        } else {
          $add_to = $add_cc;
          $add_cc = array();
        }
      }

      $add_to = array_unique($add_to);
      $add_cc = array_diff(array_unique($add_cc), $add_to);

      $mailer->addTos($add_to);
      if ($add_cc) {
        $mailer->addCCs($add_cc);
      }
    } catch (Exception $ex) {
      $this
        ->setStatus(PhabricatorMailOutboundStatus::STATUS_FAIL)
        ->setMessage($ex->getMessage())
        ->save();

      throw $ex;
    }

    try {
      $ok = $mailer->send();
      if (!$ok) {
        // TODO: At some point, we should clean this up and make all mailers
        // throw.
        throw new Exception(
          pht('Mail adapter encountered an unexpected, unspecified failure.'));
      }

      $this->setStatus(PhabricatorMailOutboundStatus::STATUS_SENT);
      $this->save();

      return $this;
    } catch (PhabricatorMetaMTAPermanentFailureException $ex) {
      $this
        ->setStatus(PhabricatorMailOutboundStatus::STATUS_FAIL)
        ->setMessage($ex->getMessage())
        ->save();

      throw $ex;
    } catch (Exception $ex) {
      $this
        ->setMessage($ex->getMessage()."\n".$ex->getTraceAsString())
        ->save();

      throw $ex;
    }
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

  public static function shouldMultiplexAllMail() {
    return PhabricatorEnv::getEnvConfig('metamta.one-mail-per-recipient');
  }


/* -(  Managing Recipients  )------------------------------------------------ */


  /**
   * Get all of the recipients for this mail, after preference filters are
   * applied. This list has all objects to whom delivery will be attempted.
   *
   * Note that this expands recipients into their members, because delivery
   * is never directly attempted to aggregate actors like projects.
   *
   * @return  list<phid>  A list of all recipients to whom delivery will be
   *                      attempted.
   * @task recipients
   */
  public function buildRecipientList() {
    $actors = $this->loadAllActors();
    $actors = $this->filterDeliverableActors($actors);
    return mpull($actors, 'getPHID');
  }

  public function loadAllActors() {
    $actor_phids = $this->getExpandedRecipientPHIDs();
    return $this->loadActors($actor_phids);
  }

  public function getExpandedRecipientPHIDs() {
    $actor_phids = $this->getAllActorPHIDs();
    return $this->expandRecipients($actor_phids);
  }

  private function getAllActorPHIDs() {
    return array_merge(
      array($this->getParam('from')),
      $this->getToPHIDs(),
      $this->getCcPHIDs());
  }

  /**
   * Expand a list of recipient PHIDs (possibly including aggregate recipients
   * like projects) into a deaggregated list of individual recipient PHIDs.
   * For example, this will expand project PHIDs into a list of the project's
   * members.
   *
   * @param list<phid>  List of recipient PHIDs, possibly including aggregate
   *                    recipients.
   * @return list<phid> Deaggregated list of mailable recipients.
   */
  private function expandRecipients(array $phids) {
    if ($this->recipientExpansionMap === null) {
      $all_phids = $this->getAllActorPHIDs();
      $this->recipientExpansionMap = id(new PhabricatorMetaMTAMemberQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs($all_phids)
        ->execute();
    }

    $results = array();
    foreach ($phids as $phid) {
      foreach ($this->recipientExpansionMap[$phid] as $recipient_phid) {
        $results[$recipient_phid] = $recipient_phid;
      }
    }

    return array_keys($results);
  }

  private function filterDeliverableActors(array $actors) {
    assert_instances_of($actors, 'PhabricatorMetaMTAActor');
    $deliverable_actors = array();
    foreach ($actors as $phid => $actor) {
      if ($actor->isDeliverable()) {
        $deliverable_actors[$phid] = $actor;
      }
    }
    return $deliverable_actors;
  }

  private function loadActors(array $actor_phids) {
    $actor_phids = array_filter($actor_phids);
    $viewer = PhabricatorUser::getOmnipotentUser();

    $actors = id(new PhabricatorMetaMTAActorQuery())
      ->setViewer($viewer)
      ->withPHIDs($actor_phids)
      ->execute();

    if (!$actors) {
      return array();
    }

    if ($this->getForceDelivery()) {
      // If we're forcing delivery, skip all the opt-out checks. We don't
      // bother annotating reasoning on the mail in this case because it should
      // always be obvious why the mail hit this rule (e.g., it is a password
      // reset mail).
      foreach ($actors as $actor) {
        $actor->setDeliverable(PhabricatorMetaMTAActor::REASON_FORCE);
      }
      return $actors;
    }

    // Exclude explicit recipients.
    foreach ($this->getExcludeMailRecipientPHIDs() as $phid) {
      $actor = idx($actors, $phid);
      if (!$actor) {
        continue;
      }
      $actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_RESPONSE);
    }

    // Before running more rules, save a list of the actors who were
    // deliverable before we started running preference-based rules. This stops
    // us from trying to send mail to disabled users just because a Herald rule
    // added them, for example.
    $deliverable = array();
    foreach ($actors as $phid => $actor) {
      if ($actor->isDeliverable()) {
        $deliverable[] = $phid;
      }
    }

    // For the rest of the rules, order matters. We're going to run all the
    // possible rules in order from weakest to strongest, and let the strongest
    // matching rule win. The weaker rules leave annotations behind which help
    // users understand why the mail was routed the way it was.

    // Exclude the actor if their preferences are set.
    $from_phid = $this->getParam('from');
    $from_actor = idx($actors, $from_phid);
    if ($from_actor) {
      $from_user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($from_phid))
        ->execute();
      $from_user = head($from_user);
      if ($from_user) {
        $pref_key = PhabricatorUserPreferences::PREFERENCE_NO_SELF_MAIL;
        $exclude_self = $from_user
          ->loadPreferences()
          ->getPreference($pref_key);
        if ($exclude_self) {
          $from_actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_SELF);
        }
      }
    }

    $all_prefs = id(new PhabricatorUserPreferences())->loadAllWhere(
      'userPHID in (%Ls)',
      $actor_phids);
    $all_prefs = mpull($all_prefs, null, 'getUserPHID');

    $value_email = PhabricatorUserPreferences::MAILTAG_PREFERENCE_EMAIL;

    // Exclude all recipients who have set preferences to not receive this type
    // of email (for example, a user who says they don't want emails about task
    // CC changes).
    $tags = $this->getParam('mailtags');
    if ($tags) {
      foreach ($all_prefs as $phid => $prefs) {
        $user_mailtags = $prefs->getPreference(
          PhabricatorUserPreferences::PREFERENCE_MAILTAGS,
          array());

        // The user must have elected to receive mail for at least one
        // of the mailtags.
        $send = false;
        foreach ($tags as $tag) {
          if (((int)idx($user_mailtags, $tag, $value_email)) == $value_email) {
            $send = true;
            break;
          }
        }

        if (!$send) {
          $actors[$phid]->setUndeliverable(
            PhabricatorMetaMTAActor::REASON_MAILTAGS);
        }
      }
    }

    foreach ($deliverable as $phid) {
      switch ($this->getRoutingRule($phid)) {
        case PhabricatorMailRoutingRule::ROUTE_AS_NOTIFICATION:
          $actors[$phid]->setUndeliverable(
            PhabricatorMetaMTAActor::REASON_ROUTE_AS_NOTIFICATION);
          break;
        case PhabricatorMailRoutingRule::ROUTE_AS_MAIL:
          $actors[$phid]->setDeliverable(
            PhabricatorMetaMTAActor::REASON_ROUTE_AS_MAIL);
          break;
        default:
          // No change.
          break;
      }
    }

    // If recipients were initially deliverable and were added by "Send me an
    // email" Herald rules, annotate them as such and make them deliverable
    // again, overriding any changes made by the "self mail" and "mail tags"
    // settings.
    $force_recipients = $this->getForceHeraldMailRecipientPHIDs();
    $force_recipients = array_fuse($force_recipients);
    if ($force_recipients) {
      foreach ($deliverable as $phid) {
        if (isset($force_recipients[$phid])) {
          $actors[$phid]->setDeliverable(
            PhabricatorMetaMTAActor::REASON_FORCE_HERALD);
        }
      }
    }

    // Exclude recipients who don't want any mail. This rule is very strong
    // and runs last.
    foreach ($all_prefs as $phid => $prefs) {
      $exclude = $prefs->getPreference(
        PhabricatorUserPreferences::PREFERENCE_NO_MAIL,
        false);
      if ($exclude) {
        $actors[$phid]->setUndeliverable(
          PhabricatorMetaMTAActor::REASON_MAIL_DISABLED);
      }
    }

    return $actors;
  }

  private function shouldRateLimitMail(array $all_recipients) {
    try {
      PhabricatorSystemActionEngine::willTakeAction(
        $all_recipients,
        new PhabricatorMetaMTAErrorMailAction(),
        1);
      return false;
    } catch (PhabricatorSystemActionRateLimitException $ex) {
      return true;
    }
  }

  public function delete() {
    $this->openTransaction();
      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE src = %s AND type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        $this->getPHID(),
        PhabricatorMetaMTAMailHasRecipientEdgeType::EDGECONST);
      $ret = parent::delete();
    $this->saveTransaction();

    return $ret;
  }

  public function generateHeaders() {
    $headers = array();

    $headers[] = array('X-Phabricator-Sent-This-Message', 'Yes');
    $headers[] = array('X-Mail-Transport-Agent', 'MetaMTA');

    // Some clients respect this to suppress OOF and other auto-responses.
    $headers[] = array('X-Auto-Response-Suppress', 'All');

    // If the message has mailtags, filter out any recipients who don't want
    // to receive this type of mail.
    $mailtags = $this->getParam('mailtags');
    if ($mailtags) {
      $tag_header = array();
      foreach ($mailtags as $mailtag) {
        $tag_header[] = '<'.$mailtag.'>';
      }
      $tag_header = implode(', ', $tag_header);
      $headers[] = array('X-Phabricator-Mail-Tags', $tag_header);
    }

    $value = $this->getParam('headers', array());
    foreach ($value as $pair) {
      list($header_key, $header_value) = $pair;

      // NOTE: If we have \n in a header, SES rejects the email.
      $header_value = str_replace("\n", ' ', $header_value);
      $headers[] = array($header_key, $header_value);
    }

    $is_bulk = $this->getParam('is-bulk');
    if ($is_bulk) {
      $headers[] = array('Precedence', 'bulk');
    }

    return $headers;
  }

  public function getDeliveredHeaders() {
    return $this->getParam('headers.sent');
  }

  public function getDeliveredActors() {
    return $this->getParam('actors.sent');
  }

  public function getDeliveredRoutingRules() {
    return $this->getParam('routing.sent');
  }

  public function getDeliveredRoutingMap() {
    return $this->getParam('routingmap.sent');
  }


/* -(  Routing  )------------------------------------------------------------ */


  public function addRoutingRule($routing_rule, $phids, $reason_phid) {
    $routing = $this->getParam('routing', array());
    $routing[] = array(
      'routingRule' => $routing_rule,
      'phids' => $phids,
      'reasonPHID' => $reason_phid,
    );
    $this->setParam('routing', $routing);

    // Throw the routing map away so we rebuild it.
    $this->routingMap = null;

    return $this;
  }

  private function getRoutingRule($phid) {
    $map = $this->getRoutingRuleMap();

    $info = idx($map, $phid, idx($map, 'default'));
    if ($info) {
      return idx($info, 'rule');
    }

    return null;
  }

  private function getRoutingRuleMap() {
    if ($this->routingMap === null) {
      $map = array();

      $routing = $this->getParam('routing', array());
      foreach ($routing as $route) {
        $phids = $route['phids'];
        if ($phids === null) {
          $phids = array('default');
        }

        foreach ($phids as $phid) {
          $new_rule = $route['routingRule'];

          $current_rule = idx($map, $phid);
          if ($current_rule === null) {
            $is_stronger = true;
          } else {
            $is_stronger = PhabricatorMailRoutingRule::isStrongerThan(
              $new_rule,
              $current_rule);
          }

          if ($is_stronger) {
            $map[$phid] = array(
              'rule' => $new_rule,
              'reason' => $route['reasonPHID'],
            );
          }
        }
      }

      $this->routingMap = $map;
    }

    return $this->routingMap;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    $actor_phids = $this->getExpandedRecipientPHIDs();
    return in_array($viewer->getPHID(), $actor_phids);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'The mail sender and message recipients can always see the mail.');
  }


}
