<?php

final class PhabricatorCalendarEventAllDayTransaction
  extends PhabricatorCalendarEventTransactionType {

  const TRANSACTIONTYPE = 'calendar.allday';

  public function generateOldValue($object) {
    return (int)$object->getIsAllDay();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsAllDay($value);

    // Adjust the flags on any other dates the event has.
    $keys = array(
      'startDateTime',
      'endDateTime',
      'untilDateTime',
    );

    foreach ($keys as $key) {
      $dict = $object->getParameter($key);
      if (!$dict) {
        continue;
      }

      $datetime = PhutilCalendarAbsoluteDateTime::newFromDictionary($dict);
      $datetime->setIsAllDay($value);

      $object->setParameter($key, $datetime->toDictionary());
    }
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s changed this to an all day event.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s converted this from an all day event.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    if ($this->getNewValue()) {
      return pht(
        '%s changed %s to an all day event.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s converted %s from an all day event.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

}
