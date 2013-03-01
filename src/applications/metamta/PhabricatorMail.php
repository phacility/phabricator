<?php

abstract class PhabricatorMail {

  private $actor;

  public function setActor(PhabricatorUser $actor) {
    $this->actor = $actor;
    return $this;
  }

  public function getActor() {
    return $this->actor;
  }

}
