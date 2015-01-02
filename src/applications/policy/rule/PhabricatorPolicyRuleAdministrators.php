<?php

final class PhabricatorPolicyRuleAdministrators
  extends PhabricatorPolicyRule {

  public function getRuleDescription() {
    return pht('administrators');
  }

  public function applyRule(PhabricatorUser $viewer, $value) {
    return $viewer->getIsAdmin();
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_NONE;
  }

}
