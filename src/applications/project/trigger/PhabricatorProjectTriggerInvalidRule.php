<?php

final class PhabricatorProjectTriggerInvalidRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'invalid';

  public function getDescription() {
    return pht(
      'Invalid rule (of type "%s").',
      $this->getRecord()->getType());
  }

  public function getSelectControlName() {
    return pht('(Invalid Rule)');
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

}
