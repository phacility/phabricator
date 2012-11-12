<?php

abstract class PhabricatorEditor extends Phobject {

  private $actor;
  private $excludeMailRecipientPHIDs = array();

  final public function setActor(PhabricatorUser $actor) {
    $this->actor = $actor;
    return $this;
  }

  final protected function getActor() {
    return $this->actor;
  }

  final protected function requireActor() {
    $actor = $this->getActor();
    if (!$actor) {
      throw new Exception('You must setActor()!');
    }
    return $actor;
  }

  final public function setExcludeMailRecipientPHIDs($phids) {
    $this->excludeMailRecipientPHIDs = $phids;
    return $this;
  }

  final protected function getExcludeMailRecipientPHIDs() {
    return $this->excludeMailRecipientPHIDs;
  }

}
