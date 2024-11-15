<?php

final class RoleCanLockRolesCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'role.can.lock';

  public function getCapabilityName() {
    return pht('Can Lock Role Membership');
  }

  public function describeCapabilityRejection() {
    return pht('You do not have permission to lock role membership.');
  }

}
