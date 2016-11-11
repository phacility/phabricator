<?php

final class PhabricatorPolicyCodexRuleDescription
  extends Phobject {

  private $description;
  private $capabilities = array();
  private $isActive = true;

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setCapabilities(array $capabilities) {
    $this->capabilities = $capabilities;
    return $this;
  }

  public function getCapabilities() {
    return $this->capabilities;
  }

  public function setIsActive($is_active) {
    $this->isActive = $is_active;
    return $this;
  }

  public function getIsActive() {
    return $this->isActive;
  }

}
