<?php

final class HeraldPholioMockAdapter extends HeraldAdapter {

  private $mock;

  public function getAdapterApplicationClass() {
    return 'PhabricatorPholioApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to mocks being created or updated.');
  }

  protected function initializeNewAdapter() {
    $this->mock = $this->newObject();
  }

  protected function newObject() {
    return new PholioMock();
  }

  public function getObject() {
    return $this->mock;
  }

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }
  public function getMock() {
    return $this->mock;
  }

  public function getAdapterContentName() {
    return pht('Pholio Mocks');
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

  public function getHeraldName() {
    return 'M'.$this->getMock()->getID();
  }

}
