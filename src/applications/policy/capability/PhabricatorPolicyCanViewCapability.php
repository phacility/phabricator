<?php

final class PhabricatorPolicyCanViewCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = self::CAN_VIEW;

  public function getCapabilityName() {
    return pht('Can View');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to view this object.');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }

}
