<?php

final class HeraldManiphestTaskAdapter extends HeraldAdapter {

  private $task;

  protected function newObject() {
    return new ManiphestTask();
  }

  public function getAdapterApplicationClass() {
    return 'PhabricatorManiphestApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to tasks being created or updated.');
  }

  public function isTestAdapterForObject($object) {
    return ($object instanceof ManiphestTask);
  }

  public function getAdapterTestDescription() {
    return pht(
      'Test rules which run when a task is created or updated.');
  }

  protected function initializeNewAdapter() {
    $this->task = $this->newObject();
  }

  public function supportsApplicationEmail() {
    return true;
  }

  public function getRepetitionOptions() {
    return array(
      HeraldRepetitionPolicyConfig::EVERY,
      HeraldRepetitionPolicyConfig::FIRST,
    );
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

  public function setTask(ManiphestTask $task) {
    $this->task = $task;
    return $this;
  }

  public function getTask() {
    return $this->task;
  }

  public function setObject($object) {
    $this->task = $object;
    return $this;
  }

  public function getObject() {
    return $this->task;
  }

  public function getAdapterContentName() {
    return pht('Maniphest Tasks');
  }

  public function getHeraldName() {
    return 'T'.$this->getTask()->getID();
  }

}
