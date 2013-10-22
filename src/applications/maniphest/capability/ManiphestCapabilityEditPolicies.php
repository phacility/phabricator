<?php

final class ManiphestCapabilityEditPolicies
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.edit.policies';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Edit Task Policies');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to edit task policies.');
  }

}
