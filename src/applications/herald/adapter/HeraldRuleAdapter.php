<?php

final class HeraldRuleAdapter extends HeraldAdapter {

  private $rule;

  protected function newObject() {
    return new HeraldRule();
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to Herald rules being created or updated.');
  }

  public function isTestAdapterForObject($object) {
    return ($object instanceof HeraldRule);
  }

  public function getAdapterTestDescription() {
    return pht(
      'Test rules which run when another Herald rule is created or '.
      'updated.');
  }

  protected function initializeNewAdapter() {
    $this->rule = $this->newObject();
  }

  public function supportsApplicationEmail() {
    return true;
  }

  public function supportsRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return true;
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
      default:
        return false;
    }
  }

  public function setRule(HeraldRule $rule) {
    $this->rule = $rule;
    return $this;
  }

  public function getRule() {
    return $this->rule;
  }

  public function setObject($object) {
    $this->rule = $object;
    return $this;
  }

  public function getObject() {
    return $this->rule;
  }

  public function getAdapterContentName() {
    return pht('Herald Rules');
  }

  public function getHeraldName() {
    return $this->getRule()->getMonogram();
  }

}
