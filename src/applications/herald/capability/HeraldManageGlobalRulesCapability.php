<?php

final class HeraldManageGlobalRulesCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'herald.global';

  public function getCapabilityName() {
    return pht('Can Manage Global Rules');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to manage global Herald rules.');
  }

}
