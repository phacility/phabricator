<?php

final class RoleDefaultJoinCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'role.default.join';

  public function getCapabilityName() {
    return pht('Default Join Policy');
  }

}
