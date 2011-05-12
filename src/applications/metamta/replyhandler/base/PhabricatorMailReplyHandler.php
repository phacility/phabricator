<?php

/*
 * Copyright 2011 Facebook, Inc.
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

  abstract public function validateMailReceiver($mail_receiver);
  abstract public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle);
  abstract public function getReplyHandlerDomain();
  abstract public function getReplyHandlerInstructions();

  public function supportsPrivateReplies() {
    return (bool)$this->getReplyHandlerDomain();
  }

  final public function supportsReplies() {
    return $this->supportsPrivateReplies() ||
           (bool)$this->getPublicReplyHandlerEmailAddress();
  }

  public function getPublicReplyHandlerEmailAddress() {
    return null;
  }

  final public function multiplexMail(
    PhabricatorMetaMTAMail $mail_template,
    array $to_handles,
    array $cc_handles) {

    $result = array();

    // If private replies are not supported, simply send one email to all
    // recipients and CCs. This covers cases where we have no reply handler,
    // or we have a public reply handler.
    if (!$this->supportsPrivateReplies()) {
      $mail = clone $mail_template;
      $mail->addTos(mpull($to_handles, 'getPHID'));
      $mail->addCCs(mpull($cc_handles, 'getPHID'));

      $reply_to = $this->getPublicReplyHandlerEmailAddress();
      if ($reply_to) {
        $mail->setReplyTo($reply_to);
      }

      $result[] = $mail;

      return $result;
    }

    // Merge all the recipients together. TODO: We could keep the CCs as real
    // CCs and send to a "noreply@domain.com" type address, but keep it simple
    // for now.
    $recipients = mpull($to_handles, null, 'getPHID') +
                  mpull($cc_handles, null, 'getPHID');

    // This grouping is just so we can use the public reply-to for any
    // recipients without a private reply-to, e.g. mailing lists.
    $groups = array();
    foreach ($recipients as $recipient) {
      $private = $this->getPrivateReplyHandlerEmailAddress($recipient);
      $groups[$private][] = $recipient;
    }

    // When multiplexing mail, explicitly include To/Cc information in the
    // message body and headers.
    $add_headers = array();

    $body = $mail_template->getBody();
    $body .= "\n";
    if ($to_handles) {
      $body .= "To: ".implode(', ', mpull($to_handles, 'getName'))."\n";
      $add_headers['X-Phabricator-To'] = $this->formatPHIDList($to_handles);
    }
    if ($cc_handles) {
      $body .= "Cc: ".implode(', ', mpull($cc_handles, 'getName'))."\n";
      $add_headers['X-Phabricator-Cc'] = $this->formatPHIDList($cc_handles);
    }

    foreach ($groups as $reply_to => $group) {
      $mail = clone $mail_template;
      $mail->addTos(mpull($group, 'getPHID'));

      $mail->setBody($body);
      foreach ($add_headers as $header => $value) {
        $mail->addHeader($header, $value);
      }

      if (!$reply_to) {
        $reply_to = $this->getPublicReplyHandlerEmailAddress();
      }

      if ($reply_to) {
        $mail->setReplyTo($reply_to);
      }

      $result[] = $mail;
    }

    return $result;
  }

  protected function formatPHIDList(array $handles) {
    $list = array();
    foreach ($handles as $handle) {
      $list[] = '<'.$handle->getPHID().'>';
    }
    return implode(', ', $list);
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

    return "{$prefix}{$receiver_id}+{$user_id}+{$hash}@{$domain}";
  }

}
