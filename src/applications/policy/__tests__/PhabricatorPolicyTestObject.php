<?php

/**
 * Configurable test object for implementing Policy unit tests.
 */
final class PhabricatorPolicyTestObject
  implements PhabricatorPolicyInterface {

  private $capabilities           = array();
  private $policies               = array();
  private $automaticCapabilities  = array();

  public function getCapabilities() {
    return $this->capabilities;
  }

  public function getPolicy($capability) {
    return idx($this->policies, $capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    $auto = idx($this->automaticCapabilities, $capability, array());
    return idx($auto, $viewer->getPHID());
  }

  public function setCapabilities(array $capabilities) {
    $this->capabilities = $capabilities;
    return $this;
  }

  public function setPolicies(array $policy_map) {
    $this->policies = $policy_map;
    return $this;
  }

  public function setAutomaticCapabilities(array $auto_map) {
    $this->automaticCapabilities = $auto_map;
    return $this;
  }

}
