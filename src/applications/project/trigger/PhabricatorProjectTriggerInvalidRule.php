<?php

final class PhabricatorProjectTriggerInvalidRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'invalid';

  public function getDescription() {
    return pht(
      'Invalid rule (of type "%s").',
      $this->getRecord()->getType());
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

}
