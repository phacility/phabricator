<?php

final class PhabricatorRolesPolicyRule
  extends PhabricatorRolesBasePolicyRule {

  public function getRuleDescription() {
    return pht('members of any role');
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    $memberships = $this->getMemberships($viewer->getPHID());
    foreach ($value as $role_phid) {
      if (isset($memberships[$role_phid])) {
        return true;
      }
    }

    return false;
  }

  public function getRuleOrder() {
    return 200;
  }

}
