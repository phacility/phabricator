<?php

final class PhabricatorCalendarImportDisableTransaction
  extends PhabricatorCalendarImportTransactionType {

  const TRANSACTIONTYPE = 'calendar.import.disable';

  public function generateOldValue($object) {
    return (int)$object->getIsDisabled();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsDisabled((int)$value);
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s disabled this import.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled this import.',
        $this->renderAuthor());
    }
  }

}
