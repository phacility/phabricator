<?php

final class PhabricatorProjectTriggerUnknownRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'unknown';

  public function getSelectControlName() {
    return pht('(Unknown Rule)');
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

  public function getRuleViewLabel() {
    return pht('Unknown Rule');
  }

  public function getRuleViewDescription($value) {
    return pht(
      'This is an unknown rule of type "%s". An administrator may have '.
      'edited or removed an extension which implements this rule type.',
      $this->getRecord()->getType());
  }

  public function getRuleViewIcon($value) {
    return id(new PHUIIconView())
      ->setIcon('fa-question-circle', 'yellow');
  }

}
