<?php

final class ManiphestCapabilityEditStatus
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.edit.status';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Edit Task Status');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to edit task status.');
  }

}
