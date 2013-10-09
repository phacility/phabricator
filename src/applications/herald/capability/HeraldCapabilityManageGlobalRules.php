<?php

final class HeraldCapabilityManageGlobalRules
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'herald.global';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Manage Global Rules');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to manage global Herald rules.');
  }

}
