<?php

abstract class PhabricatorCalendarEventDateTransaction
  extends PhabricatorCalendarEventTransactionType {

  abstract protected function getInvalidDateMessage();

  public function generateNewValue($object, $value) {
    return $value->getEpoch();
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      if ($xaction->getNewValue()->isValid()) {
        continue;
      }

      $message = $this->getInvalidDateMessage();
      $errors[] = $this->newInvalidError($message, $xaction);
    }

    return $errors;
  }

}
