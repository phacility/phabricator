<?php

final class PhabricatorCalendarEventFrequencyTransaction
  extends PhabricatorCalendarEventTransactionType {

  const TRANSACTIONTYPE = 'calendar.frequency';

  public function generateOldValue($object) {
    return $object->getFrequencyRule();
  }

  public function applyInternalEffects($object, $value) {
    $object->setRecurrenceFrequency(
      array(
        'rule' => $value,
      ));
  }

  public function getTitle() {
    $frequency = $this->getFrequencyRule($this->getNewValue());
    switch ($frequency) {
      case PhabricatorCalendarEvent::FREQUENCY_DAILY:
        return pht(
          '%s set this event to repeat daily.',
          $this->renderAuthor());
      case PhabricatorCalendarEvent::FREQUENCY_WEEKLY:
        return pht(
          '%s set this event to repeat weekly.',
          $this->renderAuthor());
      case PhabricatorCalendarEvent::FREQUENCY_MONTHLY:
        return pht(
          '%s set this event to repeat monthly.',
          $this->renderAuthor());
      case PhabricatorCalendarEvent::FREQUENCY_YEARLY:
        return pht(
          '%s set this event to repeat yearly.',
          $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $frequency = $this->getFrequencyRule($this->getNewValue());
    switch ($frequency) {
      case PhabricatorCalendarEvent::FREQUENCY_DAILY:
        return pht(
          '%s set %s to repeat daily.',
          $this->renderAuthor(),
          $this->renderObject());
      case PhabricatorCalendarEvent::FREQUENCY_WEEKLY:
        return pht(
          '%s set %s to repeat weekly.',
          $this->renderAuthor(),
          $this->renderObject());
      case PhabricatorCalendarEvent::FREQUENCY_MONTHLY:
        return pht(
          '%s set %s to repeat monthly.',
          $this->renderAuthor(),
          $this->renderObject());
      case PhabricatorCalendarEvent::FREQUENCY_YEARLY:
        return pht(
          '%s set %s to repeat yearly.',
          $this->renderAuthor(),
          $this->renderObject());
    }
  }

  private function getFrequencyRule($value) {
    if (is_array($value)) {
      $value = idx($value, 'rule');
    } else {
      return $value;
    }
  }

}
