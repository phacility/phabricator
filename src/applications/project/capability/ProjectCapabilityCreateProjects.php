<?php

final class ProjectCapabilityCreateProjects
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'project.create';

  public function getCapabilityKey() {
    return self::CAPABILITY;
  }

  public function getCapabilityName() {
    return pht('Can Create Projects');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create new projects.');
  }

}
