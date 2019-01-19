<?php

abstract class PhabricatorMailReceiver extends Phobject {

  private $viewer;
  private $sender;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setSender(PhabricatorUser $sender) {
    $this->sender = $sender;
    return $this;
  }

  final public function getSender() {
    return $this->sender;
  }

  abstract public function isEnabled();
  abstract public function canAcceptMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhutilEmailAddress $target);

  abstract protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhutilEmailAddress $target);

  final public function receiveMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhutilEmailAddress $target) {
    $this->processReceivedMail($mail, $target);
  }

}
