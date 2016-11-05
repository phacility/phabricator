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

    if ($value) {
      $datetime = PhutilCalendarAbsoluteDateTime::newFromDictionary($value);
      $datetime->setIsAllDay($editor->getNewIsAllDay());
      $object->setUntilDateTime($datetime);
    } else {
      $object->setUntilDateTime(null);
    }
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s changed this event to repeat until %s.',
        $this->renderAuthor(),
        $this->renderNewDate());
    } else {
      return pht(
        '%s changed this event to repeat forever.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    if ($this->getNewValue()) {
      return pht(
        '%s changed %s to repeat until %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderNewDate());
    } else {
      return pht(
        '%s changed %s to repeat forever.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  protected function getInvalidDateMessage() {
    return pht('Repeat until date is invalid.');
  }

}
