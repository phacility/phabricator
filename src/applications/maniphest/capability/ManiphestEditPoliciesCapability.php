<?php

final class ManiphestEditPoliciesCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.edit.policies';

  public function getCapabilityName() {
    return pht('Can Edit Task Policies');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to edit task policies.');
  }

}
