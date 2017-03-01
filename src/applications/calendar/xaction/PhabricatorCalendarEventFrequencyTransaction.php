<?php

final class PhabricatorCalendarEventFrequencyTransaction
  extends PhabricatorCalendarEventTransactionType {

  const TRANSACTIONTYPE = 'calendar.frequency';

  public function generateOldValue($object) {
    $rrule = $object->newRecurrenceRule();

    if (!$rrule) {
      return null;
    }

    return $rrule->getFrequency();
  }

  public function applyInternalEffects($object, $value) {
    $rrule = id(new PhutilCalendarRecurrenceRule())
      ->setFrequency($value);

    // If the user creates a monthly event on the 29th, 30th or 31st of a
    // month, it means "the 30th of every month" as far as the RRULE is
    // concerned. Such an event will not occur on months with fewer days.

    // This is surprising, and proably not what the user wants. Instead,
    // schedule these events relative to the end of the month: on the "-1st",
    // "-2nd" or "-3rd" day of the month. For example, a monthly event on
    // the 31st of a 31-day month translates to "every month, on the last
    // day of the month".
    if ($value == PhutilCalendarRecurrenceRule::FREQUENCY_MONTHLY) {
      $start_datetime = $object->newStartDateTime();

      $y = $start_datetime->getYear();
      $m = $start_datetime->getMonth();
      $d = $start_datetime->getDay();
      if ($d >= 29) {
        $year_map = PhutilCalendarRecurrenceRule::getYearMap(
          $y,
          PhutilCalendarRecurrenceRule::WEEKDAY_MONDAY);

        $month_days = $year_map['monthDays'][$m];
        $schedule_on = -(($month_days + 1) - $d);

        $rrule->setByMonthDay(array($schedule_on));
      }
    }

    $object->setRecurrenceRule($rrule);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $valid = array(
      PhutilCalendarRecurrenceRule::FREQUENCY_DAILY,
      PhutilCalendarRecurrenceRule::FREQUENCY_WEEKLY,
      PhutilCalendarRecurrenceRule::FREQUENCY_MONTHLY,
      PhutilCalendarRecurrenceRule::FREQUENCY_YEARLY,
    );
    $valid = array_fuse($valid);

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();

      if (!isset($valid[$value])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Event frequency "%s" is not valid. Valid frequences are: %s.',
            $value,
            implode(', ', $valid)),
          $xaction);
      }
    }

    return $errors;
  }

  public function getTitle() {
    $frequency = $this->getFrequency($this->getNewValue());
    switch ($frequency) {
      case PhutilCalendarRecurrenceRule::FREQUENCY_DAILY:
        return pht(
          '%s set this event to repeat daily.',
          $this->renderAuthor());
      case PhutilCalendarRecurrenceRule::FREQUENCY_WEEKLY:
        return pht(
          '%s set this event to repeat weekly.',
          $this->renderAuthor());
      case PhutilCalendarRecurrenceRule::FREQUENCY_MONTHLY:
        return pht(
          '%s set this event to repeat monthly.',
          $this->renderAuthor());
      case PhutilCalendarRecurrenceRule::FREQUENCY_YEARLY:
        return pht(
          '%s set this event to repeat yearly.',
          $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $frequency = $this->getFrequency($this->getNewValue());
    switch ($frequency) {
      case PhutilCalendarRecurrenceRule::FREQUENCY_DAILY:
        return pht(
          '%s set %s to repeat daily.',
          $this->renderAuthor(),
          $this->renderObject());
      case PhutilCalendarRecurrenceRule::FREQUENCY_WEEKLY:
        return pht(
          '%s set %s to repeat weekly.',
          $this->renderAuthor(),
          $this->renderObject());
      case PhutilCalendarRecurrenceRule::FREQUENCY_MONTHLY:
        return pht(
          '%s set %s to repeat monthly.',
          $this->renderAuthor(),
          $this->renderObject());
      case PhutilCalendarRecurrenceRule::FREQUENCY_YEARLY:
        return pht(
          '%s set %s to repeat yearly.',
          $this->renderAuthor(),
          $this->renderObject());
    }
  }

  private function getFrequency($value) {
    // NOTE: This is normalizing three generations of these transactions
    // to use RRULE constants. It would be vaguely nice to migrate them
    // for consistency.

    if (is_array($value)) {
      $value = idx($value, 'rule');
    } else {
      $value = $value;
    }

    return phutil_utf8_strtoupper($value);
  }

}
