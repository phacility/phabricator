<?php

abstract class PhabricatorPeopleMailEngine
  extends Phobject {

  private $sender;
  private $recipient;

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

    return $engine->markupText($text);
  }

}
