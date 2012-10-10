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

abstract class PhabricatorMailReplyHandler {

  private $mailReceiver;
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
  abstract public function getReplyHandlerDomain();
  abstract public function getReplyHandlerInstructions();
  abstract protected function receiveEmail(
    PhabricatorMetaMTAReceivedMail $mail);

  public function processEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $error = $this->sanityCheckEmail($mail);

    if ($error) {
      if ($this->shouldSendErrorEmail($mail)) {
        $this->sendErrorEmail($error, $mail);
      }
      return null;
    }

    return $this->receiveEmail($mail);
  }

  private function sanityCheckEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $body = $mail->getCleanTextBody();
    if (empty($body)) {
      return 'Empty email body. Email should begin with an !action and / or '.
             'text to comment. Inline replies and signatures are ignored.';
    }

    return null;
  }

  /**
   * Only send an error email if the user is talking to just Phabricator. We
   * can assume if there is only one To address it is a Phabricator address
   * since this code is running and everything.
   */
  private function shouldSendErrorEmail(PhabricatorMetaMTAReceivedMail $mail) {
    return count($mail->getToAddresses() == 1) &&
           count($mail->getCCAddresses() == 0);
  }

  private function sendErrorEmail($error,
                                  PhabricatorMetaMTAReceivedMail $mail) {
    $template = new PhabricatorMetaMTAMail();
    $template->setSubject('Exception: unable to process your mail request');
    $template->setBody($this->buildErrorMailBody($error, $mail));
    $template->setRelatedPHID($mail->getRelatedPHID());
    $phid = $this->getActor()->getPHID();
    $tos = array(
      $phid => PhabricatorObjectHandleData::loadOneHandle($phid)
    );
    $mails = $this->multiplexMail($template, $tos, array());

    foreach ($mails as $email) {
      $email->saveAndSend();
    }

    return true;
  }

  private function buildErrorMailBody($error,
                                      PhabricatorMetaMTAReceivedMail $mail) {
    $original_body = $mail->getRawTextBody();

    $main_body = <<<EOBODY
Your request failed because an error was encoutered while processing it:

  ERROR: {$error}

  -- Original Body -------------------------------------------------------------

  {$original_body}

EOBODY;

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection($main_body);
    $body->addReplySection($this->getReplyHandlerInstructions());

    return $body->render();
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
    $hash = PhabricatorMetaMTAReceivedMail::computeMailHash(
      $receiver->getMailKey(),
      $receiver->getPHID());

    $address = "{$prefix}{$receiver_id}+public+{$hash}@{$domain}";
    return $this->getSingleReplyHandlerPrefix($address);
  }

  protected function getSingleReplyHandlerPrefix($address) {
    $single_handle_prefix = PhabricatorEnv::getEnvConfig(
      'metamta.single-reply-handler-prefix');
    return ($single_handle_prefix)
      ? $single_handle_prefix . '+' . $address
      : $address;
  }

  protected function getDefaultPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle,
    $prefix) {

    if ($handle->getType() != PhabricatorPHIDConstants::PHID_TYPE_USER) {
      // You must be a real user to get a private reply handler address.
      return null;
    }

    $receiver = $this->getMailReceiver();
    $receiver_id = $receiver->getID();
    $user_id = $handle->getAlternateID();
    $hash = PhabricatorMetaMTAReceivedMail::computeMailHash(
      $receiver->getMailKey(),
      $handle->getPHID());
    $domain = $this->getReplyHandlerDomain();

    $address = "{$prefix}{$receiver_id}+{$user_id}+{$hash}@{$domain}";
    return $this->getSingleReplyHandlerPrefix($address);
  }

}
