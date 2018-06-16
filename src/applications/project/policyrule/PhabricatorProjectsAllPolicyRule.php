<?php

final class PhabricatorProjectsAllPolicyRule
  extends PhabricatorProjectsBasePolicyRule {

  public function getRuleDescription() {
    return pht('members of all projects');
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    $memberships = $this->getMemberships($viewer->getPHID());
    foreach ($value as $project_phid) {
      if (empty($memberships[$project_phid])) {
        return false;
      }
    }

    return true;
  }

  public function getRuleOrder() {
    return 205;
  }

}
