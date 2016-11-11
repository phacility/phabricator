<?php

abstract class PhabricatorCalendarEventDateTransaction
  extends PhabricatorCalendarEventTransactionType {

  abstract protected function getInvalidDateMessage();

  public function isInheritedEdit() {
    return false;
  }

  public function generateNewValue($object, $value) {
    $editor = $this->getEditor();

    if ($value->isDisabled()) {
      return null;
    }

    return $value->newPhutilDateTime()
      ->setIsAllDay($editor->getNewIsAllDay())
      ->newAbsoluteDateTime()
      ->toDictionary();
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
