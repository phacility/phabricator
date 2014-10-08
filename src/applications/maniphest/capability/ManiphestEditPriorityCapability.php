<?php

final class ManiphestEditPriorityCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.edit.priority';

  public function getCapabilityName() {
    return pht('Can Prioritize Tasks');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to prioritize tasks.');
  }

}
