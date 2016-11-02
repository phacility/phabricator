<?php

final class PhabricatorCalendarEventUntilDateTransaction
  extends PhabricatorCalendarEventDateTransaction {

  const TRANSACTIONTYPE = 'calendar.recurrenceenddate';

  public function generateOldValue($object) {
    $editor = $this->getEditor();

    $until = $object->newUntilDateTime();
    if (!$until) {
      return null;
    }

    return $until
      ->newAbsoluteDateTime()
      ->setIsAllDay($editor->getOldIsAllDay())
      ->toDictionary();
  }

  public function applyInternalEffects($object, $value) {
    $actor = $this->getActor();
    $editor = $this->getEditor();

    $datetime = PhutilCalendarAbsoluteDateTime::newFromDictionary($value);
    $datetime->setIsAllDay($editor->getNewIsAllDay());

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
