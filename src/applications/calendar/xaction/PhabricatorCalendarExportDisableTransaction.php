<?php

final class PhabricatorCalendarExportDisableTransaction
  extends PhabricatorCalendarExportTransactionType {

  const TRANSACTIONTYPE = 'calendar.export.disable';

  public function generateOldValue($object) {
    return (int)$object->getIsDisabled();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsDisabled((int)$value);
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s disabled this export.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled this export.',
        $this->renderAuthor());
    }
  }

}
