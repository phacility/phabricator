<?php

abstract class PhabricatorEditor extends Phobject {

  private $actor;
  private $excludeMailRecipientPHIDs = array();

  final public function setActor(PhabricatorUser $actor) {
    $this->actor = $actor;
    return $this;
  }

  final public function getActor() {
    return $this->actor;
  }

  final public function requireActor() {
    $actor = $this->getActor();
    if (!$actor) {
      throw new PhutilInvalidStateException('setActor');
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
