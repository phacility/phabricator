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

    $this->assertValidRuleRecordFormat($value);

    $this->record = $record;
    return $this;
  }

  final public function getRecord() {
    return $this->record;
  }

  final protected function getValue() {
    return $this->getRecord()->getValue();
  }

  protected function getValueForEditorField() {
    return $this->getValue();
  }

  abstract public function getSelectControlName();
  abstract public function getRuleViewLabel();
  abstract public function getRuleViewDescription($value);
  abstract public function getRuleViewIcon($value);
  abstract protected function assertValidRuleRecordFormat($value);

  final public function getRuleRecordValueValidationException() {
    try {
      $this->assertValidRuleRecordValue($this->getRecord()->getValue());
    } catch (Exception $ex) {
      return $ex;
    }

    return null;
  }

  protected function assertValidRuleRecordValue($value) {
    return;
  }

  abstract protected function newDropTransactions($object, $value);
  abstract protected function newDropEffects($value);
  abstract protected function getDefaultValue();
  abstract protected function getPHUIXControlType();
  abstract protected function getPHUIXControlSpecification();

  protected function isSelectableRule() {
    return true;
  }

  protected function isValidRule() {
    return true;
  }

  protected function newInvalidView() {
    return null;
  }

  public function getSoundEffects() {
    return array();
  }

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

  final public function getDropEffects() {
    return $this->newDropEffects($this->getValue());
  }

  final protected function newEffect() {
    return id(new PhabricatorProjectDropEffect())
      ->setIsTriggerEffect(true);
  }

  final public function toDictionary() {
    $record = $this->getRecord();

    $is_valid = $this->isValidRule();
    if (!$is_valid) {
      $invalid_view = hsprintf('%s', $this->newInvalidView());
    } else {
      $invalid_view = null;
    }

    return array(
      'type' => $record->getType(),
      'value' => $this->getValueForEditorField(),
      'isValidRule' => $is_valid,
      'invalidView' => $invalid_view,
    );
  }

  final public function newTemplate() {
    return array(
      'type' => $this->getTriggerType(),
      'name' => $this->getSelectControlName(),
      'selectable' => $this->isSelectableRule(),
      'defaultValue' => $this->getDefaultValue(),
      'control' => array(
        'type' => $this->getPHUIXControlType(),
        'specification' => $this->getPHUIXControlSpecification(),
      ),
    );
  }


}
