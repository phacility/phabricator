<?php

final class RoleDefaultViewCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'role.default.view';

  public function getCapabilityName() {
    return pht('Default View Policy');
  }

  public function shouldAllowPublicPolicySetting() {
    return true;
  }
}
