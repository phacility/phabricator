<?php

final class ManiphestCapabilityEditPriority
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.edit.priority';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Prioritize Tasks');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to prioritize tasks.');
  }

}
