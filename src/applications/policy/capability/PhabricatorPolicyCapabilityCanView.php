<?php

final class PhabricatorPolicyCapabilityCanView
  extends PhabricatorPolicyCapability {

  public function getCapabilityKey() {
    return self::CAN_VIEW;
  }

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
