<?php

abstract class PhabricatorProjectTriggerRule
  extends Phobject {

  private $record;
  private $viewer;
  private $column;
  private $trigger;
  private $object;

  final public function getTriggerType() {
    return $this->getPhobjectClassConstant('TRIGGERTYPE', 64);
  }

  final public static function getAllTriggerRules() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getTriggerType')
      ->execute();
  }

  final public function setRecord(PhabricatorProjectTriggerRuleRecord $record) {
    $value = $record->getValue();

    $this->assertValidRuleValue($value);

    $this->record = $record;
    return $this;
  }

  final public function getRecord() {
    return $this->record;
  }

  final protected function getValue() {
    return $this->getRecord()->getValue();
  }

  abstract public function getDescription();
  abstract protected function assertValidRuleValue($value);
  abstract protected function newDropTransactions($object, $value);

  final public function getDropTransactions($object, $value) {
    return $this->newDropTransactions($object, $value);
  }

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setColumn(PhabricatorProjectColumn $column) {
    $this->column = $column;
    return $this;
  }

  final public function getColumn() {
    return $this->column;
  }

  final public function setTrigger(PhabricatorProjectTrigger $trigger) {
    $this->trigger = $trigger;
    return $this;
  }

  final public function getTrigger() {
    return $this->trigger;
  }

  final public function setObject(
    PhabricatorApplicationTransactionInterface $object) {
    $this->object = $object;
    return $this;
  }

  final public function getObject() {
    return $this->object;
  }

  final protected function newTransaction() {
    return $this->getObject()->getApplicationTransactionTemplate();
  }

}
