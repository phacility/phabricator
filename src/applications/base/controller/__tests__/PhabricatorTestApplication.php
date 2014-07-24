<?php

final class PhabricatorTestApplication extends PhabricatorApplication {

  private $policies = array();

  public function getName() {
    return pht('Test');
  }

  public function isUnlisted() {
    return true;
  }

  public function isLaunchable() {
    return false;
  }

  public function reset() {
    $this->policies = array();
  }

  public function setPolicy($capability, $value) {
    $this->policies[$capability] = $value;
    return $this;
  }

  public function getPolicy($capability) {
    return idx($this->policies, $capability, parent::getPolicy($capability));
  }

  public function canUninstall() {
    return false;
  }

  public function getRoutes() {
    return array();
  }

}
