<?php

final class PhabricatorProjectTriggerInvalidRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'invalid';

  private $exception;

  public function setException(Exception $exception) {
    $this->exception = $exception;
    return $this;
  }

  public function getException() {
    return $this->exception;
  }

  public function getSelectControlName() {
    return pht('(Invalid Rule)');
  }

  protected function isSelectableRule() {
    return false;
  }

  protected function assertValidRuleRecordFormat($value) {
    return;
  }

  protected function newDropTransactions($object, $value) {
    return array();
  }

  protected function newDropEffects($value) {
    return array();
  }

  protected function isValidRule() {
    return false;
  }

  protected function newInvalidView() {
    return array(
      id(new PHUIIconView())
        ->setIcon('fa-exclamation-triangle red'),
      ' ',
      pht(
        'This is a trigger rule with a valid type ("%s") but an invalid '.
        'value.',
        $this->getRecord()->getType()),
    );
  }

  protected function getDefaultValue() {
    return null;
  }

  protected function getPHUIXControlType() {
    return null;
  }

  protected function getPHUIXControlSpecification() {
    return null;
  }

  public function getRuleViewLabel() {
    return pht('Invalid Rule');
  }

  public function getRuleViewDescription($value) {
    $record = $this->getRecord();
    $type = $record->getType();

    $exception = $this->getException();
    if ($exception) {
      return pht(
        'This rule (of type "%s") is invalid: %s',
        $type,
        $exception->getMessage());
    } else {
      return pht(
        'This rule (of type "%s") is invalid.',
        $type);
    }
  }

  public function getRuleViewIcon($value) {
    return id(new PHUIIconView())
      ->setIcon('fa-exclamation-triangle', 'red');
  }

}
