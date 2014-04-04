<?php

final class ManiphestCapabilityEditProjects
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'maniphest.edit.projects';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Edit Task Projects');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to edit task projects.');
  }

}
