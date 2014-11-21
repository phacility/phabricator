<?php

final class ProjectDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'project.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
