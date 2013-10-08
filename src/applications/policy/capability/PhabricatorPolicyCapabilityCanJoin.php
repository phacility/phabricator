<?php

final class PhabricatorPolicyCapabilityCanJoin
  extends PhabricatorPolicyCapability {

  public function getCapabilityKey() {
    return self::CAN_JOIN;
  }

  public function getCapabilityName() {
    return pht('Can Join');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to join this object.');
  }

}
