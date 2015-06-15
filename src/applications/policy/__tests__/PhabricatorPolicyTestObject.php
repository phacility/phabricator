<?php

/**
 * Configurable test object for implementing Policy unit tests.
 */
final class PhabricatorPolicyTestObject
  extends Phobject
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface {

  private $phid;
  private $capabilities = array();
  private $policies = array();
  private $automaticCapabilities = array();
  private $extendedPolicies = array();

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function getPHID() {
    return $this->phid;
  }

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

  public function describeAutomaticCapability($capability) {
    return null;
  }

  public function setExtendedPolicies(array $extended_policies) {
    $this->extendedPolicies = $extended_policies;
    return $this;
  }

  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    return idx($this->extendedPolicies, $capability, array());
  }

}
