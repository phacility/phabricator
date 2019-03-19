<?php

final class PhabricatorProjectTriggerUnknownRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'unknown';

  public function getDescription() {
    return pht(
      'Unknown rule (of type "%s").',
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
