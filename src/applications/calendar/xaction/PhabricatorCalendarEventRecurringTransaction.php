<?php

final class PhabricatorCalendarEventRecurringTransaction
  extends PhabricatorCalendarEventTransactionType {

  const TRANSACTIONTYPE = 'calendar.recurring';

  public function generateOldValue($object) {
    return (int)$object->getIsRecurring();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsRecurring($value);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $old = $object->getIsRecurring();
    foreach ($xactions as $xaction) {
      if ($this->isNewObject()) {
        continue;
      }

      if ($xaction->getNewValue() == $old) {
        continue;
      }

      $errors[] = $this->newInvalidError(
        pht(
          'An event can only be made recurring when it is created. '.
          'You can not convert an existing event into a recurring '.
          'event or vice versa.'),
        $xaction);
    }

    return $errors;
  }

}
