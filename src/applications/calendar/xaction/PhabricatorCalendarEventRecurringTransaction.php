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

      if ($xaction->getNewValue()) {
        continue;
      }

      $errors[] = $this->newInvalidError(
        pht(
          'An event can not be stopped from recurring once it has been '.
          'made recurring. You can cancel the event.'),
        $xaction);
    }

    return $errors;
  }

}
