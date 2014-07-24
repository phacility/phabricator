<?php

final class PhabricatorMacroManageCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'macro.manage';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Manage Macros');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to manage image macros.');
  }

}
