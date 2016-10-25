<?php

final class PhabricatorCalendarImportReloadTransaction
  extends PhabricatorCalendarImportTransactionType {

  const TRANSACTIONTYPE = 'calendar.import.reload';

  public function generateOldValue($object) {
    return false;
  }

  public function applyExternalEffects($object, $value) {
    // NOTE: This transaction does nothing directly; instead, the Editor
    // reacts to it and performs the reload.
  }

  public function getTitle() {
    return pht(
      '%s reloaded this event source.',
      $this->renderAuthor());
  }

}
