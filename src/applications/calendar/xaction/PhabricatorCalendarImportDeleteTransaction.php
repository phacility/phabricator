<?php

final class PhabricatorCalendarImportDeleteTransaction
  extends PhabricatorCalendarImportTransactionType {

  const TRANSACTIONTYPE = 'calendar.import.delete';

  public function generateOldValue($object) {
    return false;
  }

  public function applyExternalEffects($object, $value) {
    $events = id(new PhabricatorCalendarEventQuery())
      ->setViewer($this->getActor())
      ->withImportSourcePHIDs(array($object->getPHID()))
      ->execute();

    $engine = new PhabricatorDestructionEngine();
    foreach ($events as $event) {
      $engine->destroyObject($event);
    }
  }

  public function getTitle() {
    return pht(
      '%s deleted imported events from this source.',
      $this->renderAuthor());
  }

}
