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

final class PhabricatorMetaMTAReceivedMail extends PhabricatorMetaMTADAO {

  protected $headers = array();
  protected $bodies = array();
  protected $attachments = array();

  protected $relatedPHID;
  protected $authorPHID;
  protected $message;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'headers'     => self::SERIALIZATION_JSON,
        'bodies'      => self::SERIALIZATION_JSON,
        'attachments' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function setHeaders(array $headers) {
    // Normalize headers to lowercase.
    $normalized = array();
    foreach ($headers as $name => $value) {
      $normalized[strtolower($name)] = $value;
    }
    $this->headers = $normalized;
    return $this;
  }

  public function getMessageID() {
    return idx($this->headers, 'message-id');
  }

  public function getSubject() {
    return idx($this->headers, 'subject');
  }

  public function getCCAddresses() {
    return $this->getRawEmailAddresses(idx($this->headers, 'cc'));
  }

  public function getToAddresses() {
    return $this->getRawEmailAddresses(idx($this->headers, 'to'));
  }

  private function loadExcludeMailRecipientPHIDs() {
    $addresses = array_merge(
      $this->getToAddresses(),
      $this->getCCAddresses()
    );

    return $this->loadPHIDsFromAddresses($addresses);
  }

  final public function loadCCPHIDs() {
    return $this->loadPHIDsFromAddresses($this->getCCAddresses());
  }

  private function loadPHIDsFromAddresses(array $addresses) {
    $users = id(new PhabricatorUserEmail())
      ->loadAllWhere('address IN (%Ls)', $addresses);
    $user_phids = mpull($users, 'getUserPHID');

    $mailing_lists = id(new PhabricatorMetaMTAMailingList())
      ->loadAllWhere('email in (%Ls)', $addresses);
    $mailing_list_phids = mpull($mailing_lists, 'getPHID');

    return array_merge($user_phids,  $mailing_list_phids);
  }

  /**
   * Parses "to" addresses, looking for a public create email address
   * first and if not found parsing the "to" address for reply handler
   * information: receiver name, user id, and hash.
   */
  private function getPhabricatorToInformation() {
    // Only one "public" create address so far
    $create_task = PhabricatorEnv::getEnvConfig(
      'metamta.maniphest.public-create-email');

    // For replies, look for an object address with a format like:
    // D291+291+b0a41ca848d66dcc@example.com
    $single_handle_prefix = PhabricatorEnv::getEnvConfig(
      'metamta.single-reply-handler-prefix');

    $prefixPattern = ($single_handle_prefix)
      ? preg_quote($single_handle_prefix, '/') . '\+'
      : '';
    $pattern = "/^{$prefixPattern}((?:D|T|C)\d+)\+([\w]+)\+([a-f0-9]{16})@/U";

    $phabricator_address = null;
    $receiver_name       = null;
    $user_id             = null;
    $hash                = null;
    foreach ($this->getToAddresses() as $address) {
      if ($address == $create_task) {
        $phabricator_address = $address;
        // it's okay to stop here because we just need to map a create
        // address to an application and don't need / won't have more
        // information in these cases.
        break;
      }

      $matches = null;
      $ok = preg_match(
        $pattern,
        $address,
        $matches);

      if ($ok) {
        $phabricator_address = $address;
        $receiver_name       = $matches[1];
        $user_id             = $matches[2];
        $hash                = $matches[3];
        break;
      }
    }

    return array(
      $phabricator_address,
      $receiver_name,
      $user_id,
      $hash
    );
  }


  public function processReceivedMail() {

    // If Phabricator sent the mail, always drop it immediately. This prevents
    // loops where, e.g., the public bug address is also a user email address
    // and creating a bug sends them an email, which loops.
    $is_phabricator_mail = idx(
      $this->headers,
      'x-phabricator-sent-this-message');
    if ($is_phabricator_mail) {
      $message = "Ignoring email with 'X-Phabricator-Sent-This-Message' ".
                 "header to avoid loops.";
      return $this->setMessage($message)->save();
    }

    list($to,
         $receiver_name,
         $user_id,
         $hash) = $this->getPhabricatorToInformation();
    if (!$to) {
      $raw_to = idx($this->headers, 'to');
      return $this->setMessage("Unrecognized 'to' format: {$raw_to}")->save();
    }

    $from = idx($this->headers, 'from');

    // TODO -- make this a switch statement / better if / when we add more
    // public create email addresses!
    $create_task = PhabricatorEnv::getEnvConfig(
      'metamta.maniphest.public-create-email');

    if ($create_task && $to == $create_task) {
      $receiver = new ManiphestTask();

      $user = $this->lookupPublicUser();
      if ($user) {
        $this->setAuthorPHID($user->getPHID());
      } else {
        $default_author = PhabricatorEnv::getEnvConfig(
          'metamta.maniphest.default-public-author');

        if ($default_author) {
          $user = id(new PhabricatorUser())->loadOneWhere(
            'username = %s',
            $default_author);
          if ($user) {
            $receiver->setOriginalEmailSource($from);
          } else {
            throw new Exception(
              "Phabricator is misconfigured, the configuration key ".
              "'metamta.maniphest.default-public-author' is set to user ".
              "'{$default_author}' but that user does not exist.");
          }
        } else {
          // TODO: We should probably bounce these since from the user's
          // perspective their email vanishes into a black hole.
          return $this->setMessage("Invalid public user '{$from}'.")->save();
        }
      }

      $receiver->setAuthorPHID($user->getPHID());
      $receiver->setPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);

      $editor = new ManiphestTransactionEditor();
      $editor->setActor($user);
      $handler = $editor->buildReplyHandler($receiver);

      $handler->setActor($user);
      $handler->setExcludeMailRecipientPHIDs(
        $this->loadExcludeMailRecipientPHIDs());
      $handler->processEmail($this);

      $this->setRelatedPHID($receiver->getPHID());
      $this->setMessage('OK');

      return $this->save();
    }

    if ($user_id == 'public') {
      if (!PhabricatorEnv::getEnvConfig('metamta.public-replies')) {
        return $this->setMessage("Public replies not enabled.")->save();
      }

      $user = $this->lookupPublicUser();

      if (!$user) {
        return $this->setMessage("Invalid public user '{$from}'.")->save();
      }

      $use_user_hash = false;
    } else {
      $user = id(new PhabricatorUser())->load($user_id);
      if (!$user) {
        return $this->setMessage("Invalid private user '{$user_id}'.")->save();
      }

      $use_user_hash = true;
    }

    if ($user->getIsDisabled()) {
      return $this->setMessage("User '{$user_id}' is disabled")->save();
    }

    $this->setAuthorPHID($user->getPHID());

    $receiver = self::loadReceiverObject($receiver_name);
    if (!$receiver) {
      return $this->setMessage("Invalid object '{$receiver_name}'")->save();
    }

    $this->setRelatedPHID($receiver->getPHID());

    if ($use_user_hash) {
      // This is a private reply-to address, check that the user hash is
      // correct.
      $check_phid = $user->getPHID();
    } else {
      // This is a public reply-to address, check that the object hash is
      // correct.
      $check_phid = $receiver->getPHID();
    }

    $expect_hash = self::computeMailHash($receiver->getMailKey(), $check_phid);

    // See note at computeOldMailHash().
    $old_hash = self::computeOldMailHash($receiver->getMailKey(), $check_phid);

    if ($expect_hash != $hash && $old_hash != $hash) {
      return $this->setMessage("Invalid mail hash!")->save();
    }

    if ($receiver instanceof ManiphestTask) {
      $editor = new ManiphestTransactionEditor();
      $editor->setActor($user);
      $handler = $editor->buildReplyHandler($receiver);
    } else if ($receiver instanceof DifferentialRevision) {
      $handler = DifferentialMail::newReplyHandlerForRevision($receiver);
    } else if ($receiver instanceof PhabricatorRepositoryCommit) {
      $handler = PhabricatorAuditCommentEditor::newReplyHandlerForCommit(
        $receiver);
    }

    $handler->setActor($user);
    $handler->setExcludeMailRecipientPHIDs(
      $this->loadExcludeMailRecipientPHIDs());
    $handler->processEmail($this);

    $this->setMessage('OK');

    return $this->save();
  }

  public function getCleanTextBody() {
    $body = idx($this->bodies, 'text');

    $parser = new PhabricatorMetaMTAEmailBodyParser();
    return $parser->stripTextBody($body);
  }

  public function getRawTextBody() {
    return idx($this->bodies, 'text');
  }

  public static function loadReceiverObject($receiver_name) {
    if (!$receiver_name) {
      return null;
    }

    $receiver_type = $receiver_name[0];
    $receiver_id   = substr($receiver_name, 1);

    $class_obj = null;
    switch ($receiver_type) {
      case 'T':
        $class_obj = new ManiphestTask();
        break;
      case 'D':
        $class_obj = new DifferentialRevision();
        break;
      case 'C':
        $class_obj = new PhabricatorRepositoryCommit();
        break;
      default:
        return null;
    }

    return $class_obj->load($receiver_id);
  }

  public static function computeMailHash($mail_key, $phid) {
    $global_mail_key = PhabricatorEnv::getEnvConfig('phabricator.mail-key');

    $hash = PhabricatorHash::digest($mail_key.$global_mail_key.$phid);
    return substr($hash, 0, 16);
  }

  public static function computeOldMailHash($mail_key, $phid) {

    // TODO: Remove this method entirely in a couple of months. We've moved from
    // plain sha1 to sha1+hmac to make the codebase more auditable for good uses
    // of hash functions, but still accept the old hashes on email replies to
    // avoid breaking things. Once we've been sending only hmac hashes for a
    // while, remove this and start rejecting old hashes. See T547.

    $global_mail_key = PhabricatorEnv::getEnvConfig('phabricator.mail-key');

    $hash = sha1($mail_key.$global_mail_key.$phid);
    return substr($hash, 0, 16);
  }

  /**
   * Strip an email address down to the actual user@domain.tld part if
   * necessary, since sometimes it will have formatting like
   * '"Abraham Lincoln" <alincoln@logcab.in>'.
   */
  private function getRawEmailAddress($address) {
    $matches = null;
    $ok = preg_match('/<(.*)>/', $address, $matches);
    if ($ok) {
      $address = $matches[1];
    }
    return $address;
  }

  private function getRawEmailAddresses($addresses) {
    $raw_addresses = array();
    foreach (explode(',', $addresses) as $address) {
      $raw_addresses[] = $this->getRawEmailAddress($address);
    }
    return $raw_addresses;
  }

  private function lookupPublicUser() {
    $from = idx($this->headers, 'from');
    $from = $this->getRawEmailAddress($from);

    $user = PhabricatorUser::loadOneWithEmailAddress($from);

    // If Phabricator is configured to allow "Reply-To" authentication, try
    // the "Reply-To" address if we failed to match the "From" address.
    $config_key = 'metamta.insecure-auth-with-reply-to';
    $allow_reply_to = PhabricatorEnv::getEnvConfig($config_key);

    if (!$user && $allow_reply_to) {
      $reply_to = idx($this->headers, 'reply-to');
      $reply_to = $this->getRawEmailAddress($reply_to);
      if ($reply_to) {
        $user = PhabricatorUser::loadOneWithEmailAddress($reply_to);
      }
    }

    return $user;
  }

}
