<?php

final class PhutilCalendarRecurrenceTestCase extends PhutilTestCase {

  public function testCalendarRecurrenceLists() {
    $set = id(new PhutilCalendarRecurrenceSet());
    $result = $set->getEventsBetween(null, null, 0xFFFF);
    $this->assertEqual(
      array(),
      $result,
      pht('Set with no sources.'));


    $set = id(new PhutilCalendarRecurrenceSet())
      ->addSource(new PhutilCalendarRecurrenceList());
    $result = $set->getEventsBetween(null, null, 0xFFFF);
    $this->assertEqual(
      array(),
      $result,
      pht('Set with empty list source.'));


    $list = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T120000Z'),
    );

    $source = id(new PhutilCalendarRecurrenceList())
      ->setDates($list);

    $set = id(new PhutilCalendarRecurrenceSet())
      ->addSource($source);

    $expect = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
    );

    $result = $set->getEventsBetween(null, null, 0xFFFF);
    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Simple date list.'));

    $list_a = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
    );

    $list_b = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T120000Z'),
    );

    $source_a = id(new PhutilCalendarRecurrenceList())
      ->setDates($list_a);

    $source_b = id(new PhutilCalendarRecurrenceList())
      ->setDates($list_b);

    $set = id(new PhutilCalendarRecurrenceSet())
      ->addSource($source_a)
      ->addSource($source_b);

    $expect = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
    );

    $result = $set->getEventsBetween(null, null, 0xFFFF);
    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Multiple date lists.'));

    $list_a = array(
      // This is Jan 1, 3, 5, 7, 8 and 10, but listed out-of-order.
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160105T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160108T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160110T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160107T120000Z'),
    );

    $list_b = array(
      // This is Jan 2, 4, 5, 8, but listed out of order.
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160104T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160105T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160108T120000Z'),
    );

    $list_c = array(
      // We're going to use this as an exception list.

      // This is Jan 7 (listed in one other source), 8 (listed in two)
      // and 9 (listed in none).
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160107T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160108T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160109T120000Z'),
    );

    $expect = array(
      // From source A.
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
      // From source B.
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T120000Z'),
      // From source A.
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
      // From source B.
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160104T120000Z'),
      // From source A and B. Should appear only once.
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160105T120000Z'),
      // The 6th appears in no source.
      // The 7th, 8th and 9th are excluded.
      // The 10th is from source A.
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160110T120000Z'),
    );

    $list_a = id(new PhutilCalendarRecurrenceList())
      ->setDates($list_a);

    $list_b = id(new PhutilCalendarRecurrenceList())
      ->setDates($list_b);

    $list_c = id(new PhutilCalendarRecurrenceList())
      ->setDates($list_c)
      ->setIsExceptionSource(true);

    $date_set = id(new PhutilCalendarRecurrenceSet())
      ->addSource($list_b)
      ->addSource($list_c)
      ->addSource($list_a);

    $date_set->setViewerTimezone('UTC');

    $result = $date_set->getEventsBetween(null, null, 0xFFFF);
    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Set of all results in multiple lists with exclusions.'));


    $expect = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
    );
    $result = $date_set->getEventsBetween(null, null, 1);
    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Multiple lists, one result.'));

    $expect = array(
      2 => PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
      3 => PhutilCalendarAbsoluteDateTime::newFromISO8601('20160104T120000Z'),
    );
    $result = $date_set->getEventsBetween(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160104T120000Z'));
    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Multiple lists, time window.'));
  }

  public function testCalendarRecurrenceOffsets() {
    $list = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T120000Z'),
    );

    $source = id(new PhutilCalendarRecurrenceList())
      ->setDates($list);

    $set = id(new PhutilCalendarRecurrenceSet())
      ->addSource($source);

    $t1 = PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T120001Z');
    $t2 = PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z');

    $expect = array(
      2 => $t2,
    );

    $result = $set->getEventsBetween($t1, null, 0xFFFF);
    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Correct event indexes with start date.'));
  }

}
