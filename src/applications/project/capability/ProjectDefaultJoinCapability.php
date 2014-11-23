<?php

final class ProjectDefaultJoinCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'project.default.join';

  public function getCapabilityName() {
    return pht('Default Join Policy');
  }

}
