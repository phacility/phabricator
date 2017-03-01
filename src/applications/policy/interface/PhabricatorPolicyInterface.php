<?php

interface PhabricatorPolicyInterface extends PhabricatorPHIDInterface {

  public function getCapabilities();
  public function getPolicy($capability);
  public function hasAutomaticCapability($capability, PhabricatorUser $viewer);

}

// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */
/*

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::POLICY_USER;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

*/
