<?php

final class PhabricatorAuthTemporaryToken extends PhabricatorAuthDAO
  implements PhabricatorPolicyInterface {

  protected $objectPHID;
  protected $tokenType;
  protected $tokenExpires;
  protected $tokenCode;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    // We're just implement this interface to get access to the standard
    // query infrastructure.
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }


}
