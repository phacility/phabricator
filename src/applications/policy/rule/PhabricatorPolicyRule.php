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

}
