<?php

final class PhabricatorCalendarEventStartDateTransaction
  extends PhabricatorCalendarEventDateTransaction {

  const TRANSACTIONTYPE = 'calendar.startdate';

  public function generateOldValue($object) {
    // TODO: Upgrade this.
    return $object->getStartDateTimeEpoch();
  }

  public function applyInternalEffects($object, $value) {
    $actor = $this->getActor();

    $datetime = PhutilCalendarAbsoluteDateTime::newFromEpoch(
      $value,
      $actor->getTimezoneIdentifier());
    $datetime->setIsAllDay($object->getIsAllDay());
    $object->setStartDateTime($datetime);
  }

  public function getTitle() {
    return pht(
      '%s changed the start date for this event from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldDate(),
      $this->renderNewDate());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the start date for %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldDate(),
      $this->renderNewDate());
  }

  protected function getInvalidDateMessage() {
    return pht('Start date is invalid.');
  }

}
