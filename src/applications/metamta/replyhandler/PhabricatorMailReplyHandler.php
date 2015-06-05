<?php

abstract class PhabricatorMailReplyHandler {

  private $mailReceiver;
  private $applicationEmail;
  private $actor;
  private $excludePHIDs = array();

  final public function setMailReceiver($mail_receiver) {
    $this->validateMailReceiver($mail_receiver);
    $this->mailReceiver = $mail_receiver;
    return $this;
  }

  final public function getMailReceiver() {
    return $this->mailReceiver;
  }

  public function setApplicationEmail(
    PhabricatorMetaMTAApplicationEmail $email) {
    $this->applicationEmail = $email;
    return $this;
  }

  public function getApplicationEmail() {
    return $this->applicationEmail;
  }

  final public function setActor(PhabricatorUser $actor) {
    $this->actor = $actor;
    return $this;
  }

  final public function getActor() {
    return $this->actor;
  }

  final public function setExcludeMailRecipientPHIDs(array $exclude) {
    $this->excludePHIDs = $exclude;
    return $this;
  }

  final public function getExcludeMailRecipientPHIDs() {
    return $this->excludePHIDs;
  }

  abstract public function validateMailReceiver($mail_receiver);
  abstract public function getPrivateReplyHandlerEmailAddress(
    PhabricatorUser $user);

  public function getReplyHandlerDomain() {
    return PhabricatorEnv::getEnvConfig('metamta.reply-handler-domain');
  }

  abstract protected function receiveEmail(
    PhabricatorMetaMTAReceivedMail $mail);

  public function processEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $this->dropEmptyMail($mail);

    return $this->receiveEmail($mail);
  }

  private function dropEmptyMail(PhabricatorMetaMTAReceivedMail $mail) {
    $body = $mail->getCleanTextBody();
    $attachments = $mail->getAttachments();

    if (strlen($body) || $attachments) {
      return;
    }

     // Only send an error email if the user is talking to just Phabricator.
     // We can assume if there is only one "To" address it is a Phabricator
     // address since this code is running and everything.
    $is_direct_mail = (count($mail->getToAddresses()) == 1) &&
                      (count($mail->getCCAddresses()) == 0);

    if ($is_direct_mail) {
      $status_code = MetaMTAReceivedMailStatus::STATUS_EMPTY;
    } else {
      $status_code = MetaMTAReceivedMailStatus::STATUS_EMPTY_IGNORED;
    }

    throw new PhabricatorMetaMTAReceivedMailProcessingException(
      $status_code,
      pht(
        'Your message does not contain any body text or attachments, so '.
        'Phabricator can not do anything useful with it. Make sure comment '.
        'text appears at the top of your message: quoted replies, inline '.
        'text, and signatures are discarded and ignored.'));
  }

  public function supportsPrivateReplies() {
    return (bool)$this->getReplyHandlerDomain() &&
           !$this->supportsPublicReplies();
  }

  public function supportsPublicReplies() {
    if (!PhabricatorEnv::getEnvConfig('metamta.public-replies')) {
      return false;
    }

    if (!$this->getReplyHandlerDomain()) {
      return false;
    }

    return (bool)$this->getPublicReplyHandlerEmailAddress();
  }

  final public function supportsReplies() {
    return $this->supportsPrivateReplies() ||
           $this->supportsPublicReplies();
  }

  public function getPublicReplyHandlerEmailAddress() {
    return null;
  }

  protected function getDefaultPublicReplyHandlerEmailAddress($prefix) {

    $receiver = $this->getMailReceiver();
    $receiver_id = $receiver->getID();
    $domain = $this->getReplyHandlerDomain();

    // We compute a hash using the object's own PHID to prevent an attacker
    // from blindly interacting with objects that they haven't ever received
    // mail about by just sending to D1@, D2@, etc...
    $hash = PhabricatorObjectMailReceiver::computeMailHash(
      $receiver->getMailKey(),
      $receiver->getPHID());

    $address = "{$prefix}{$receiver_id}+public+{$hash}@{$domain}";
    return $this->getSingleReplyHandlerPrefix($address);
  }

  protected function getSingleReplyHandlerPrefix($address) {
    $single_handle_prefix = PhabricatorEnv::getEnvConfig(
      'metamta.single-reply-handler-prefix');
    return ($single_handle_prefix)
      ? $single_handle_prefix.'+'.$address
      : $address;
  }

  protected function getDefaultPrivateReplyHandlerEmailAddress(
    PhabricatorUser $user,
    $prefix) {

    $receiver = $this->getMailReceiver();
    $receiver_id = $receiver->getID();
    $user_id = $user->getID();
    $hash = PhabricatorObjectMailReceiver::computeMailHash(
      $receiver->getMailKey(),
      $user->getPHID());
    $domain = $this->getReplyHandlerDomain();

    $address = "{$prefix}{$receiver_id}+{$user_id}+{$hash}@{$domain}";
    return $this->getSingleReplyHandlerPrefix($address);
  }

  final protected function enhanceBodyWithAttachments(
    $body,
    array $attachments) {

    if (!$attachments) {
      return $body;
    }

    $files = id(new PhabricatorFileQuery())
      ->setViewer($this->getActor())
      ->withPHIDs($attachments)
      ->execute();

    $output = array();
    $output[] = $body;

    // We're going to put all the non-images first in a list, then embed
    // the images.
    $head = array();
    $tail = array();
    foreach ($files as $file) {
      if ($file->isViewableImage()) {
        $tail[] = $file;
      } else {
        $head[] = $file;
      }
    }

    if ($head) {
      $list = array();
      foreach ($head as $file) {
        $list[] = '  - {'.$file->getMonogram().', layout=link}';
      }
      $output[] = implode("\n", $list);
    }

    if ($tail) {
      $list = array();
      foreach ($tail as $file) {
        $list[] = '{'.$file->getMonogram().'}';
      }
      $output[] = implode("\n\n", $list);
    }

    $output = implode("\n\n", $output);

    return rtrim($output);
  }


  /**
   * Produce a list of mail targets for a given to/cc list.
   *
   * Each target should be sent a separate email, and contains the information
   * required to generate it with appropriate permissions and configuration.
   *
   * @param list<phid> List of "To" PHIDs.
   * @param list<phid> List of "CC" PHIDs.
   * @return list<PhabricatorMailTarget> List of targets.
   */
  final public function getMailTargets(array $raw_to, array $raw_cc) {
    list($to, $cc) = $this->expandRecipientPHIDs($raw_to, $raw_cc);
    list($to, $cc) = $this->loadRecipientUsers($to, $cc);
    list($to, $cc) = $this->filterRecipientUsers($to, $cc);

    if (!$to && !$cc) {
      return array();
    }

    $template = id(new PhabricatorMailTarget())
      ->setRawToPHIDs($raw_to)
      ->setRawCCPHIDs($raw_cc);

    // Set the public reply address as the default, if one exists. We
    // might replace this with a private address later.
    if ($this->supportsPublicReplies()) {
      $reply_to = $this->getPublicReplyHandlerEmailAddress();
      if ($reply_to) {
        $template->setReplyTo($reply_to);
      }
    }

    $supports_private_replies = $this->supportsPrivateReplies();
    $mail_all = !PhabricatorEnv::getEnvConfig('metamta.one-mail-per-recipient');
    $targets = array();
    if ($mail_all) {
      $target = id(clone $template)
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->setToMap($to)
        ->setCCMap($cc);

      $targets[] = $target;
    } else {
      $map = $to + $cc;

      foreach ($map as $phid => $user) {
        $target = id(clone $template)
          ->setViewer($user)
          ->setToMap(array($phid => $user))
          ->setCCMap(array());

        if ($supports_private_replies) {
          $reply_to = $this->getPrivateReplyHandlerEmailAddress($user);
          if ($reply_to) {
            $target->setReplyTo($reply_to);
          }
        }

        $targets[] = $target;
      }
    }

    return $targets;
  }


  /**
   * Expand lists of recipient PHIDs.
   *
   * This takes any compound recipients (like projects) and looks up all their
   * members.
   *
   * @param list<phid> List of To PHIDs.
   * @param list<phid> List of CC PHIDs.
   * @return pair<list<phid>, list<phid>> Expanded PHID lists.
   */
  private function expandRecipientPHIDs(array $to, array $cc) {
    $to_result = array();
    $cc_result = array();

    $all_phids = array_merge($to, $cc);
    if ($all_phids) {
      $map = id(new PhabricatorMetaMTAMemberQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs($all_phids)
        ->execute();
      foreach ($to as $phid) {
        foreach ($map[$phid] as $expanded) {
          $to_result[$expanded] = $expanded;
        }
      }
      foreach ($cc as $phid) {
        foreach ($map[$phid] as $expanded) {
          $cc_result[$expanded] = $expanded;
        }
      }
    }

    // Remove recipients from "CC" if they're also present in "To".
    $cc_result = array_diff_key($cc_result, $to_result);

    return array(array_values($to_result), array_values($cc_result));
  }


  /**
   * Load @{class:PhabricatorUser} objects for each recipient.
   *
   * Invalid recipients are dropped from the results.
   *
   * @param list<phid> List of To PHIDs.
   * @param list<phid> List of CC PHIDs.
   * @return pair<wild, wild> Maps from PHIDs to users.
   */
  private function loadRecipientUsers(array $to, array $cc) {
    $to_result = array();
    $cc_result = array();

    $all_phids = array_merge($to, $cc);
    if ($all_phids) {
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs($all_phids)
        ->execute();
      $users = mpull($users, null, 'getPHID');

      foreach ($to as $phid) {
        if (isset($users[$phid])) {
          $to_result[$phid] = $users[$phid];
        }
      }
      foreach ($cc as $phid) {
        if (isset($users[$phid])) {
          $cc_result[$phid] = $users[$phid];
        }
      }
    }

    return array($to_result, $cc_result);
  }


  /**
   * Remove recipients who do not have permission to view the mail receiver.
   *
   * @param map<string, PhabricatorUser> Map of "To" users.
   * @param map<string, PhabricatorUser> Map of "CC" users.
   * @return pair<wild, wild> Filtered user maps.
   */
  private function filterRecipientUsers(array $to, array $cc) {
    $to_result = array();
    $cc_result = array();

    $all_users = $to + $cc;
    if ($all_users) {
      $can_see = array();
      $object = $this->getMailReceiver();
      foreach ($all_users as $phid => $user) {
        $visible = PhabricatorPolicyFilter::hasCapability(
          $user,
          $object,
          PhabricatorPolicyCapability::CAN_VIEW);
        if ($visible) {
          $can_see[$phid] = true;
        }
      }

      foreach ($to as $phid => $user) {
        if (!empty($can_see[$phid])) {
          $to_result[$phid] = $all_users[$phid];
        }
      }

      foreach ($cc as $phid => $user) {
        if (!empty($can_see[$phid])) {
          $cc_result[$phid] = $all_users[$phid];
        }
      }
    }

    return array($to_result, $cc_result);
  }

}
