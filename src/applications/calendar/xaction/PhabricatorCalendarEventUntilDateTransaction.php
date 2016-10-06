<?php

final class PhabricatorCalendarEventUntilDateTransaction
  extends PhabricatorCalendarEventDateTransaction {

  const TRANSACTIONTYPE = 'calendar.recurrenceenddate';

  public function generateOldValue($object) {
    // TODO: Upgrade this.
    return $object->getUntilDateTimeEpoch();
  }

  public function applyInternalEffects($object, $value) {
    $actor = $this->getActor();

    // TODO: DEPRECATED.
    $object->setRecurrenceEndDate($value);

    $datetime = PhutilCalendarAbsoluteDateTime::newFromEpoch(
      $value,
      $actor->getTimezoneIdentifier());
    $datetime->setIsAllDay($object->getIsAllDay());
    $object->setUntilDateTime($datetime);
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
