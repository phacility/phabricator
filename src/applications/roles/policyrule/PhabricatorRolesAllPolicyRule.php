<?php

final class PhabricatorRolesAllPolicyRule
  extends PhabricatorRolesBasePolicyRule {

  public function getRuleDescription() {
    return pht('members of all roles');
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    $memberships = $this->getMemberships($viewer->getPHID());
    foreach ($value as $role_phid) {
      if (empty($memberships[$role_phid])) {
        return false;
      }
    }

    return true;
  }

  public function getRuleOrder() {
    return 205;
  }

}
