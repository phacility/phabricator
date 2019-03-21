<?php

final class PhabricatorProjectTriggerUnknownRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'unknown';

  public function getDescription() {
    return pht(
      'Unknown rule (of type "%s").',
      $this->getRecord()->getType());
  }

  public function getSelectControlName() {
    return pht('(Unknown Rule)');
  }

  protected function isSelectableRule() {
    return false;
  }

  protected function assertValidRuleValue($value) {
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
        ->setIcon('fa-exclamation-triangle yellow'),
      ' ',
      pht(
        'This is a trigger rule with a unknown type ("%s").',
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

}
