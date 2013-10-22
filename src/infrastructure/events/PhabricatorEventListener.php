<?php

abstract class PhabricatorEventListener extends PhutilEventListener {

  private $application;

  public function setApplication(PhabricatorApplication $application) {
    $this->application = $application;
    return $this;
  }

  public function getApplication() {
    return $this->application;
  }

  public function hasApplicationCapability(
    PhabricatorUser $viewer,
    $capability) {
    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $this->getApplication(),
      $capability);
  }

  public function canUseApplication(PhabricatorUser $viewer) {
    return $this->hasApplicationCapability(
      $viewer,
      PhabricatorPolicyCapability::CAN_VIEW);
  }

}





