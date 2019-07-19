<?php

abstract class PhabricatorPeopleMailEngine
  extends Phobject {

  private $sender;
  private $recipient;
  private $recipientAddress;

  final public function setSender(PhabricatorUser $sender) {
    $this->sender = $sender;
    return $this;
  }

  final public function getSender() {
    if (!$this->sender) {
      throw new PhutilInvalidStateException('setSender');
    }
    return $this->sender;
  }

  final public function setRecipient(PhabricatorUser $recipient) {
    $this->recipient = $recipient;
    return $this;
  }

  final public function getRecipient() {
    if (!$this->recipient) {
      throw new PhutilInvalidStateException('setRecipient');
    }
    return $this->recipient;
  }

  final public function setRecipientAddress(PhutilEmailAddress $address) {
    $this->recipientAddress = $address;
    return $this;
  }

  final public function getRecipientAddress() {
    if (!$this->recipientAddress) {
      throw new PhutilInvalidStateException('recipientAddress');
    }
    return $this->recipientAddress;
  }

  final public function hasRecipientAddress() {
    return ($this->recipientAddress !== null);
  }

  final public function canSendMail() {
    try {
      $this->validateMail();
      return true;
    } catch (PhabricatorPeopleMailEngineException $ex) {
      return false;
    }
  }

  final public function sendMail() {
    $this->validateMail();
    $mail = $this->newMail();

    if ($this->hasRecipientAddress()) {
      $recipient_address = $this->getRecipientAddress();
      $mail->addRawTos(array($recipient_address->getAddress()));
    } else {
      $recipient = $this->getRecipient();
      $mail->addTos(array($recipient->getPHID()));
    }

    $mail
      ->setForceDelivery(true)
      ->save();

    return $mail;
  }

  abstract public function validateMail();
  abstract protected function newMail();

  final protected function throwValidationException($title, $body) {
    throw new PhabricatorPeopleMailEngineException($title, $body);
  }

  final protected function newRemarkupText($text) {
    $recipient = $this->getRecipient();

    $engine = PhabricatorMarkupEngine::newMarkupEngine(array())
      ->setConfig('viewer', $recipient)
      ->setConfig('uri.base', PhabricatorEnv::getProductionURI('/'))
      ->setMode(PhutilRemarkupEngine::MODE_TEXT);

    $rendered_text = $engine->markupText($text);
    $rendered_text = rtrim($rendered_text, "\n");

    return $rendered_text;
  }

}
