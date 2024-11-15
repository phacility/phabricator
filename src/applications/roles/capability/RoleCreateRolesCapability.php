<?php

final class RoleCreateRolesCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'role.create';

  public function getCapabilityName() {
    return pht('Can Create Roles');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to create new roles.');
  }

}
