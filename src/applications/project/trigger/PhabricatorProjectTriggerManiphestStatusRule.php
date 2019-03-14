<?php

final class PhabricatorProjectTriggerManiphestStatusRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'task.status';

  public function getDescription() {
    $value = $this->getValue();

    return pht(
      'Changes status to "%s".',
      ManiphestTaskStatus::getTaskStatusName($value));
  }

  protected function assertValidRuleValue($value) {
    if (!is_string($value)) {
      throw new Exception(
        pht(
          'Status rule value should be a string, but is not (value is "%s").',
          phutil_describe_type($value)));
    }

    $map = ManiphestTaskStatus::getTaskStatusMap();
    if (!isset($map[$value])) {
      throw new Exception(
        pht(
          'Rule value ("%s") is not a valid task status.',
          $value));
    }
  }

  protected function newDropTransactions($object, $value) {
    return array(
      $this->newTransaction()
        ->setTransactionType(ManiphestTaskStatusTransaction::TRANSACTIONTYPE)
        ->setNewValue($value),
    );
  }

}
