<?php

final class PhabricatorCalendarEventStartDateTransaction
  extends PhabricatorCalendarEventDateTransaction {

  const TRANSACTIONTYPE = 'calendar.startdate';

  public function generateOldValue($object) {
    return $object->getDateFrom();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDateFrom($value);
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
