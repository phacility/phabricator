<?php

final class PhabricatorCalendarEventUntilDateTransaction
  extends PhabricatorCalendarEventDateTransaction {

  const TRANSACTIONTYPE = 'calendar.recurrenceenddate';

  public function generateOldValue($object) {
    return $object->getRecurrenceEndDate();
  }

  public function applyInternalEffects($object, $value) {
    $object->setRecurrenceEndDate($value);
  }

  public function getTitle() {
    return pht(
      '%s changed this event to repeat until %s.',
      $this->renderAuthor(),
      $this->renderNewDate());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed %s to repeat until %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderNewDate());
  }

  protected function getInvalidDateMessage() {
    return pht('Repeat until date is invalid.');
  }

}
