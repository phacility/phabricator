<?php

final class ProjectCanLockProjectsCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'project.can.lock';

  public function getCapabilityName() {
    return pht('Can Lock Project Membership');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to lock project membership.');
  }

}
