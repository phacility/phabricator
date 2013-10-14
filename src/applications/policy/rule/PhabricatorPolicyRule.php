<?php

abstract class PhabricatorPolicyRule {

  const CONTROL_TYPE_TEXT       = 'text';
  const CONTROL_TYPE_SELECT     = 'select';
  const CONTROL_TYPE_TOKENIZER  = 'tokenizer';
  const CONTROL_TYPE_NONE       = 'none';

  abstract public function getRuleDescription();
  abstract public function applyRule(PhabricatorUser $viewer, $value);

  public function willApplyRules(PhabricatorUser $viewer, array $values) {
    return;
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_TEXT;
  }

  public function getValueControlTemplate() {
    return null;
  }

  public function getRuleOrder() {
    return 500;
  }

  public function getValueForStorage($value) {
    return $value;
  }

  public function getValueForDisplay(PhabricatorUser $viewer, $value) {
    return $value;
  }

  /**
   * Return true if the given value creates a rule with a meaningful effect.
   * An example of a rule with no meaningful effect is a "users" rule with no
   * users specified.
   *
   * @return bool True if the value creates a meaningful rule.
   */
  public function ruleHasEffect($value) {
    return true;
  }

}
