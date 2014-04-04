<?php

final class ManiphestCapabilityEditAssign
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.edit.assign';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Assign Tasks');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to assign tasks.');
  }

}
