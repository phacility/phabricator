<?php

final class PhabricatorCalendarEventHeraldAdapter extends HeraldAdapter {

  private $object;

  public function getAdapterApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to events being created or updated.');
  }

  protected function newObject() {
    return new PhabricatorCalendarEvent();
  }

  public function isTestAdapterForObject($object) {
    return ($object instanceof PhabricatorCalendarEvent);
  }

  public function getAdapterTestDescription() {
    return pht(
      'Test rules which run when an event is created or updated.');
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function getAdapterContentName() {
    return pht('Calendar Events');
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
    return $this->getObject()->getMonogram();
  }

}
