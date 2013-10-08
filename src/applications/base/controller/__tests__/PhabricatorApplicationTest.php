<?php

final class PhabricatorApplicationTest extends PhabricatorApplication {

  private $policies = array();

  public function isUnlisted() {
    return true;
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

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function canUninstall() {
    return false;
  }

  public function getRoutes() {
    return array(
    );
  }

}

