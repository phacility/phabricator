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
    PhabricatorObjectHandle $handle);

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

  final public function getRecipientsSummary(
    array $to_handles,
    array $cc_handles) {
    assert_instances_of($to_handles, 'PhabricatorObjectHandle');
    assert_instances_of($cc_handles, 'PhabricatorObjectHandle');

    $body = '';

    if (PhabricatorEnv::getEnvConfig('metamta.recipients.show-hints')) {
      if ($to_handles) {
        $body .= "To: ".implode(', ', mpull($to_handles, 'getName'))."\n";
      }
      if ($cc_handles) {
        $body .= "Cc: ".implode(', ', mpull($cc_handles, 'getName'))."\n";
      }
    }

    return $body;
  }

  final public function getRecipientsSummaryHTML(
    array $to_handles,
    array $cc_handles) {
    assert_instances_of($to_handles, 'PhabricatorObjectHandle');
    assert_instances_of($cc_handles, 'PhabricatorObjectHandle');

    if (PhabricatorEnv::getEnvConfig('metamta.recipients.show-hints')) {
      $body = array();
      if ($to_handles) {
        $body[] = phutil_tag('strong', array(), 'To: ');
        $body[] = phutil_implode_html(', ', mpull($to_handles, 'getName'));
        $body[] = phutil_tag('br');
      }
      if ($cc_handles) {
        $body[] = phutil_tag('strong', array(), 'Cc: ');
        $body[] = phutil_implode_html(', ', mpull($cc_handles, 'getName'));
        $body[] = phutil_tag('br');
      }
      return phutil_tag('div', array(), $body);
    } else {
      return '';
    }

  }

  final public function multiplexMail(
    PhabricatorMetaMTAMail $mail_template,
    array $to_handles,
    array $cc_handles) {
    assert_instances_of($to_handles, 'PhabricatorObjectHandle');
    assert_instances_of($cc_handles, 'PhabricatorObjectHandle');

    $result = array();

    // If MetaMTA is configured to always multiplex, skip the single-email
    // case.
    if (!PhabricatorMetaMTAMail::shouldMultiplexAllMail()) {
      // If private replies are not supported, simply send one email to all
      // recipients and CCs. This covers cases where we have no reply handler,
      // or we have a public reply handler.
      if (!$this->supportsPrivateReplies()) {
        $mail = clone $mail_template;
        $mail->addTos(mpull($to_handles, 'getPHID'));
        $mail->addCCs(mpull($cc_handles, 'getPHID'));

        if ($this->supportsPublicReplies()) {
          $reply_to = $this->getPublicReplyHandlerEmailAddress();
          $mail->setReplyTo($reply_to);
        }

        $result[] = $mail;

        return $result;
      }
    }

    // TODO: This is pretty messy. We should really be doing all of this
    // multiplexing in the task queue, but that requires significant rewriting
    // in the general case. ApplicationTransactions can do it fairly easily,
    // but other mail sites currently can not, so we need to support this
    // junky version until they catch up and we can swap things over.

    $to_handles = $this->expandRecipientHandles($to_handles);
    $cc_handles = $this->expandRecipientHandles($cc_handles);

    $tos = mpull($to_handles, null, 'getPHID');
    $ccs = mpull($cc_handles, null, 'getPHID');

    // Merge all the recipients together. TODO: We could keep the CCs as real
    // CCs and send to a "noreply@domain.com" type address, but keep it simple
    // for now.
    $recipients = $tos + $ccs;

    // When multiplexing mail, explicitly include To/Cc information in the
    // message body and headers.

    $mail_template = clone $mail_template;

    $mail_template->addPHIDHeaders('X-Phabricator-To', array_keys($tos));
    $mail_template->addPHIDHeaders('X-Phabricator-Cc', array_keys($ccs));

    $body = $mail_template->getBody();
    $body .= "\n";
    $body .= $this->getRecipientsSummary($to_handles, $cc_handles);

    $html_body = $mail_template->getHTMLBody();
    if (strlen($html_body)) {
      $html_body .= hsprintf('%s',
        $this->getRecipientsSummaryHTML($to_handles, $cc_handles));
    }

    foreach ($recipients as $phid => $recipient) {

      $mail = clone $mail_template;
      if (isset($to_handles[$phid])) {
        $mail->addTos(array($phid));
      } else if (isset($cc_handles[$phid])) {
        $mail->addCCs(array($phid));
      } else {
        // not good - they should be a to or a cc
        continue;
      }

      $mail->setBody($body);
      $mail->setHTMLBody($html_body);

      $reply_to = null;
      if (!$reply_to && $this->supportsPrivateReplies()) {
        $reply_to = $this->getPrivateReplyHandlerEmailAddress($recipient);
      }

      if (!$reply_to && $this->supportsPublicReplies()) {
        $reply_to = $this->getPublicReplyHandlerEmailAddress();
      }

      if ($reply_to) {
        $mail->setReplyTo($reply_to);
      }

      $result[] = $mail;
    }

    return $result;
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
    PhabricatorObjectHandle $handle,
    $prefix) {

    if ($handle->getType() != PhabricatorPeopleUserPHIDType::TYPECONST) {
      // You must be a real user to get a private reply handler address.
      return null;
    }

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($handle->getPHID()))
      ->executeOne();

    if (!$user) {
      // This may happen if a user was subscribed to something, and was then
      // deleted.
      return null;
    }

    $receiver = $this->getMailReceiver();
    $receiver_id = $receiver->getID();
    $user_id = $user->getID();
    $hash = PhabricatorObjectMailReceiver::computeMailHash(
      $receiver->getMailKey(),
      $handle->getPHID());
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

  private function expandRecipientHandles(array $handles) {
    if (!$handles) {
      return array();
    }

    $phids = mpull($handles, 'getPHID');
    $results = id(new PhabricatorMetaMTAMemberQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($phids)
      ->executeExpansion();

    return id(new PhabricatorHandleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($results)
      ->execute();
  }

}
