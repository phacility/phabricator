<?php

final class PhabricatorPolicyCanInteractCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = self::CAN_INTERACT;

  public function getCapabilityName() {
    return pht('Can Interact');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to interact with this object.');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
