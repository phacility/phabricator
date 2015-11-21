<?php

final class PhabricatorAdministratorsPolicyRule extends PhabricatorPolicyRule {

  public function getRuleDescription() {
    return pht('administrators');
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {
    return $viewer->getIsAdmin();
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_NONE;
  }

}
