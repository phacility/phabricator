<?php

final class PhabricatorCalendarImportNameTransaction
  extends PhabricatorCalendarImportTransactionType {

  const TRANSACTIONTYPE = 'calendar.import.name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!strlen($old)) {
      return pht(
        '%s named this import %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else if (!strlen($new)) {
      return pht(
        '%s removed the name of this import (was: %s).',
        $this->renderAuthor(),
        $this->renderOldValue());
    } else {
      return pht(
        '%s renamed this import from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

}
