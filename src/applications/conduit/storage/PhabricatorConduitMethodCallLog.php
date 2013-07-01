<?php

/**
 * @group conduit
 */
final class PhabricatorConduitMethodCallLog extends PhabricatorConduitDAO
  implements PhabricatorPolicyInterface {

  protected $callerPHID;
  protected $connectionID;
  protected $method;
  protected $error;
  protected $duration;


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
