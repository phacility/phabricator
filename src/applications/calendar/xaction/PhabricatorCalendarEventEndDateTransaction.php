<?php

final class PhabricatorCalendarEventEndDateTransaction
  extends PhabricatorCalendarEventDateTransaction {

  const TRANSACTIONTYPE = 'calendar.enddate';

  public function generateOldValue($object) {
    // TODO: Upgrade this.
    return $object->getEndDateTimeEpoch();
  }

  public function applyInternalEffects($object, $value) {
    $actor = $this->getActor();

    $datetime = PhutilCalendarAbsoluteDateTime::newFromEpoch(
      $value,
      $actor->getTimezoneIdentifier());
    $datetime->setIsAllDay($object->getIsAllDay());
    $object->setEndDateTime($datetime);
  }

  public function getTitle() {
    return pht(
      '%s changed the end date for this event from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldDate(),
      $this->renderNewDate());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the end date for %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldDate(),
      $this->renderNewDate());
  }

  protected function getInvalidDateMessage() {
    return pht('End date is invalid.');
  }

}
