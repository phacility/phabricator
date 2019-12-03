<?php

final class PhutilCalendarDateTimeTestCase extends PhutilTestCase {

  public function testDateTimeDuration() {
    $start = PhutilCalendarAbsoluteDateTime::newFromISO8601('20161128T090000Z')
      ->setTimezone('America/Los_Angeles')
      ->setViewerTimezone('America/Chicago')
      ->setIsAllDay(true);

    $this->assertEqual(
      '20161128',
      $start->getISO8601());

    $end = $start
      ->newAbsoluteDateTime()
      ->setHour(0)
      ->setMinute(0)
      ->setSecond(0)
      ->newRelativeDateTime('P1D')
      ->newAbsoluteDateTime();

    $this->assertEqual(
      '20161129',
      $end->getISO8601());

    // This is a date which explicitly has no specified timezone.
    $start = PhutilCalendarAbsoluteDateTime::newFromISO8601('20161128', null)
      ->setViewerTimezone('UTC');

    $this->assertEqual(
      '20161128',
      $start->getISO8601());

    $end = $start
      ->newAbsoluteDateTime()
      ->setHour(0)
      ->setMinute(0)
      ->setSecond(0)
      ->newRelativeDateTime('P1D')
      ->newAbsoluteDateTime();

    $this->assertEqual(
      '20161129',
      $end->getISO8601());
  }


}
