<?php

final class RoleDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'role.default.edit';

  public function getCapabilityName() {
    return pht('Default Edit Policy');
  }

}
