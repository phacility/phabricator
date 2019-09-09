<?php

final class PhutilCalendarRecurrenceRuleTestCase extends PhutilTestCase {

  public function testSimpleRecurrenceRules() {
    $start = PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z');

    $rrule = id(new PhutilCalendarRecurrenceRule())
      ->setStartDateTime($start)
      ->setFrequency(PhutilCalendarRecurrenceRule::FREQUENCY_DAILY);

    $set = id(new PhutilCalendarRecurrenceSet())
      ->addSource($rrule);

    $result = $set->getEventsBetween(null, null, 3);

    $expect = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
    );

    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Simple daily event.'));



    $rrule = id(new PhutilCalendarRecurrenceRule())
      ->setStartDateTime($start)
      ->setFrequency(PhutilCalendarRecurrenceRule::FREQUENCY_HOURLY)
      ->setByHour(array(12, 13));

    $set = id(new PhutilCalendarRecurrenceSet())
      ->addSource($rrule);

    $result = $set->getEventsBetween(null, null, 5);

    $expect = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T130000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160102T130000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160103T120000Z'),
    );

    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Hourly event with BYHOUR.'));


    $rrule = id(new PhutilCalendarRecurrenceRule())
      ->setStartDateTime($start)
      ->setFrequency(PhutilCalendarRecurrenceRule::FREQUENCY_YEARLY);

    $set = id(new PhutilCalendarRecurrenceSet())
      ->addSource($rrule);

    $result = $set->getEventsBetween(null, null, 2);

    $expect = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20170101T120000Z'),
    );

    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Yearly event.'));


    // This is an efficiency test for bizarre rules: it defines a secondly
    // event which only occurs one a year, and generates 3 instances of it.
    // This implementation should be fast enough that this test doesn't take
    // a significant amount of time.

    $rrule = id(new PhutilCalendarRecurrenceRule())
      ->setStartDateTime($start)
      ->setFrequency(PhutilCalendarRecurrenceRule::FREQUENCY_SECONDLY)
      ->setByMonth(array(1))
      ->setByMonthDay(array(1))
      ->setByHour(array(12))
      ->setByMinute(array(0))
      ->setBySecond(array(0));

    $set = id(new PhutilCalendarRecurrenceSet())
      ->addSource($rrule);

    $result = $set->getEventsBetween(null, null, 3);

    $expect = array(
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20160101T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20170101T120000Z'),
      PhutilCalendarAbsoluteDateTime::newFromISO8601('20180101T120000Z'),
    );

    $this->assertEqual(
      mpull($expect, 'getISO8601'),
      mpull($result, 'getISO8601'),
      pht('Secondly event with many constraints.'));
  }

  public function testYearlyRecurrenceRules() {
    $tests = array();
    $expect = array();

    $tests[] = array();
    $expect[] = array(
      '19970902',
      '19980902',
      '19990902',
    );

    $tests[] = array(
      'INTERVAL' => 2,
    );
    $expect[] = array(
      '19970902',
      '19990902',
      '20010902',
    );

    $tests[] = array(
      'DTSTART' => '20000229',
    );
    $expect[] = array(
      '20000229',
      '20040229',
      '20080229',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
    );
    $expect[] = array(
      '19980102',
      '19980302',
      '19990102',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(1, 3),
    );
    $expect[] = array(
      '19970903',
      '19971001',
      '19971003',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYMONTHDAY' => array(5, 7),
    );
    $expect[] = array(
      '19980105',
      '19980107',
      '19980305',
    );

    $tests[] = array(
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19970902',
      '19970904',
      '19970909',
    );

    $tests[] = array(
      'BYDAY' => array('SU'),
    );
    $expect[] = array(
      '19970907',
      '19970914',
      '19970921',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101',
      '19980106',
      '19980108',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101',
      '19980203',
      '19980303',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
      'BYMONTH' => array(1, 3),
    );
    $expect[] = array(
      '19980101',
      '19980303',
      '20010301',
    );

    $tests[] = array(
      'BYDAY' => array('1TU', '-1TH'),
    );
    $expect[] = array(
      '19971225',
      '19980106',
      '19981231',
    );

    // Same test as above, just making sure the optional "+" syntax works.
    $tests[] = array(
      'BYDAY' => array('+1TU', '-1TH'),
    );
    $expect[] = array(
      '19971225',
      '19980106',
      '19981231',
    );

    $tests[] = array(
      'BYDAY' => array('3TU', '-3TH'),
    );
    $expect[] = array(
      '19971211',
      '19980120',
      '19981217',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYDAY' => array('1TU', '-1TH'),
    );
    $expect[] = array(
      '19980106',
      '19980129',
      '19980303',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYDAY' => array('3TU', '-3TH'),
    );
    $expect[] = array(
      '19980115',
      '19980120',
      '19980312',
    );

    $tests[] = array(
      'BYYEARDAY' => array(1, 100, 200, 365),
      'COUNT' => 4,
    );
    $expect[] = array(
      '19971231',
      '19980101',
      '19980410',
      '19980719',
    );

    $tests[] = array(
      'BYYEARDAY' => array(-365, -266, -166, -1),
      'COUNT' => 4,
    );
    $expect[] = array(
      '19971231',
      '19980101',
      '19980410',
      '19980719',
    );

    $tests[] = array(
      'BYYEARDAY' => array(1, 100, 200, 365),
      'BYMONTH' => array(4, 7),
      'COUNT' => 4,
    );
    $expect[] = array(
      '19980410',
      '19980719',
      '19990410',
      '19990719',
    );

    $tests[] = array(
      'BYYEARDAY' => array(-365, -266, -166, -1),
      'BYMONTH' => array(4, 7),
      'COUNT' => 4,
    );
    $expect[] = array(
      '19980410',
      '19980719',
      '19990410',
      '19990719',
    );

    $tests[] = array(
      'BYWEEKNO' => array(20),
    );
    $expect[] = array(
      '19980511',
      '19980512',
      '19980513',
    );

    $tests[] = array(
      'BYWEEKNO' => array(1),
      'BYDAY' => array('MO'),
    );
    $expect[] = array(
      '19971229',
      '19990104',
      '20000103',
    );

    $tests[] = array(
      'BYWEEKNO' => array(52),
      'BYDAY' => array('SU'),
    );
    $expect[] = array(
      '19971228',
      '19981227',
      '20000102',
    );

    $tests[] = array(
      'BYWEEKNO' => array(-1),
      'BYDAY' => array('SU'),
    );
    $expect[] = array(
      '19971228',
      '19990103',
      '20000102',
    );

    $tests[] = array(
      'BYWEEKNO' => array(53),
      'BYDAY' => array('MO'),
    );
    $expect[] = array(
      '19981228',
      '20041227',
      '20091228',
    );

    $tests[] = array(
      'BYHOUR' => array(6, 18),
    );
    $expect[] = array(
      '19970902T060000Z',
      '19970902T180000Z',
      '19980902T060000Z',
    );

    $tests[] = array(
      'BYMINUTE' => array(15, 30),
    );
    $expect[] = array(
      '19970902T001500Z',
      '19970902T003000Z',
      '19980902T001500Z',
    );

    $tests[] = array(
      'BYSECOND' => array(10, 20),
    );
    $expect[] = array(
      '19970902T000010Z',
      '19970902T000020Z',
      '19980902T000010Z',
    );

    $tests[] = array(
      'BYHOUR' => array(6, 18),
      'BYMINUTE' => array(15, 30),
    );
    $expect[] = array(
      '19970902T061500Z',
      '19970902T063000Z',
      '19970902T181500Z',
    );

    $tests[] = array(
      'BYHOUR' => array(6, 18),
      'BYSECOND' => array(10, 20),
    );
    $expect[] = array(
      '19970902T060010Z',
      '19970902T060020Z',
      '19970902T180010Z',
    );

    $tests[] = array(
      'BYMINUTE' => array(15, 30),
      'BYSECOND' => array(10, 20),
    );
    $expect[] = array(
      '19970902T001510Z',
      '19970902T001520Z',
      '19970902T003010Z',
    );

    $tests[] = array(
      'BYHOUR' => array(6, 18),
      'BYMINUTE' => array(15, 30),
      'BYSECOND' => array(10, 20),
    );
    $expect[] = array(
      '19970902T061510Z',
      '19970902T061520Z',
      '19970902T063010Z',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(15),
      'BYHOUR' => array(6, 18),
      'BYSETPOS' => array(3, -3),
    );
    $expect[] = array(
      '19971115T180000Z',
      '19980215T060000Z',
      '19981115T180000Z',
    );

    $this->assertRules(
      array(
        'FREQ' => 'YEARLY',
        'COUNT' => 3,
        'DTSTART' => '19970902',
      ),
      $tests,
      $expect);
  }

  public function testMonthlyRecurrenceRules() {
    $tests = array();
    $expect = array();

    $tests[] = array();
    $expect[] = array(
      '19970902',
      '19971002',
      '19971102',
    );

    $tests[] = array(
      'INTERVAL' => 2,
    );
    $expect[] = array(
      '19970902',
      '19971102',
      '19980102',
    );

    $tests[] = array(
      'INTERVAL' => 18,
    );
    $expect[] = array(
      '19970902',
      '19990302',
      '20000902',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
    );
    $expect[] = array(
      '19980102',
      '19980302',
      '19990102',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(1, 3),
    );
    $expect[] = array(
      '19970903',
      '19971001',
      '19971003',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(5, 7),
      'BYMONTH' => array(1, 3),
    );
    $expect[] = array(
      '19980105',
      '19980107',
      '19980305',
    );

    $tests[] = array(
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19970902',
      '19970904',
      '19970909',
    );

    $tests[] = array(
      'BYDAY' => array('3MO'),
    );
    $expect[] = array(
      '19970915',
      '19971020',
      '19971117',
    );

    $tests[] = array(
      'BYDAY' => array('1TU', '-1TH'),
    );
    $expect[] = array(
      '19970902',
      '19970925',
      '19971007',
    );

    $tests[] = array(
      'BYDAY' => array('3TU', '-3TH'),
    );
    $expect[] = array(
      '19970911',
      '19970916',
      '19971016',
    );

    $tests[] = array(
      'BYDAY' => array('TU', 'TH'),
      'BYMONTH' => array(1, 3),
    );
    $expect[] = array(
      '19980101',
      '19980106',
      '19980108',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYDAY' => array('1TU', '-1TH'),
    );
    $expect[] = array(
      '19980106',
      '19980129',
      '19980303',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYDAY' => array('3TU', '-3TH'),
    );
    $expect[] = array(
      '19980115',
      '19980120',
      '19980312',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101',
      '19980203',
      '19980303',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYMONTHDAY' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101',
      '19980303',
      '20010301',
    );

    $tests[] = array(
      'BYDAY' => array('MO', 'TU', 'WE', 'TH', 'FR'),
      'BYSETPOS' => array(-1),
    );
    $expect[] = array(
      '19970930',
      '19971031',
      '19971128',
    );

    $tests[] = array(
      'BYDAY' => array('1MO', '1TU', '1WE', '1TH', '1FR', '-1FR'),
      'BYMONTHDAY' => array(1, -1, -2),
    );
    $expect[] = array(
      '19971001',
      '19971031',
      '19971201',
    );

    $tests[] = array(
      'BYDAY' => array('1MO', '1TU', '1WE', '1TH', 'FR'),
      'BYMONTHDAY' => array(1, -1, -2),
    );
    $expect[] = array(
      '19971001',
      '19971031',
      '19971201',
    );

    $tests[] = array(
      'BYHOUR' => array(6, 18),
    );
    $expect[] = array(
      '19970902T060000Z',
      '19970902T180000Z',
      '19971002T060000Z',
    );

    $tests[] = array(
      'BYMINUTE' => array(6, 18),
    );
    $expect[] = array(
      '19970902T000600Z',
      '19970902T001800Z',
      '19971002T000600Z',
    );

    $tests[] = array(
      'BYSECOND' => array(6, 18),
    );
    $expect[] = array(
      '19970902T000006Z',
      '19970902T000018Z',
      '19971002T000006Z',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(13, 17),
      'BYHOUR' => array(6, 18),
      'BYSETPOS' => array(3, -3),
    );
    $expect[] = array(
      '19970913T180000Z',
      '19970917T060000Z',
      '19971013T180000Z',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(13, 17),
      'BYHOUR' => array(6, 18),
      'BYSETPOS' => array(3, 3, -3),
    );
    $expect[] = array(
      '19970913T180000Z',
      '19970917T060000Z',
      '19971013T180000Z',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(13, 17),
      'BYHOUR' => array(6, 18),
      'BYSETPOS' => array(4, -1),
    );
    $expect[] = array(
      '19970917T180000Z',
      '19971017T180000Z',
      '19971117T180000Z',
    );

    $this->assertRules(
      array(
        'FREQ' => 'MONTHLY',
        'COUNT' => 3,
        'DTSTART' => '19970902',
      ),
      $tests,
      $expect);
  }

  public function testWeeklyRecurrenceRules() {
    $tests = array();
    $expect = array();

    $tests[] = array();
    $expect[] = array(
      '19970902',
      '19970909',
      '19970916',
    );

    $tests[] = array(
      'INTERVAL' => 2,
    );
    $expect[] = array(
      '19970902',
      '19970916',
      '19970930',
    );

    $tests[] = array(
      'INTERVAL' => 20,
    );
    $expect[] = array(
      '19970902',
      '19980120',
      '19980609',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
    );
    $expect[] = array(
      '19980106',
      '19980113',
      '19980120',
    );

    $tests[] = array(
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19970902',
      '19970904',
      '19970909',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101',
      '19980106',
      '19980108',
    );

    $tests[] = array(
      'BYHOUR' => array(6, 18),
    );
    $expect[] = array(
      '19970902T060000Z',
      '19970902T180000Z',
      '19970909T060000Z',
    );

    $tests[] = array(
      'BYDAY' => array('TU', 'TH'),
      'BYHOUR' => array(6, 18),
      'BYSETPOS' => array(3, -3),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T180000Z',
      '19970904T060000Z',
      '19970909T180000Z',
    );

    $this->assertRules(
      array(
        'FREQ' => 'WEEKLY',
        'COUNT' => 3,
        'DTSTART' => '19970902',
      ),
      $tests,
      $expect);
  }

  public function testDailyRecurrenceRules() {
    $tests = array();
    $expect = array();

    $tests[] = array();
    $expect[] = array(
      '19970902',
      '19970903',
      '19970904',
    );

    $tests[] = array(
      'INTERVAL' => 2,
    );
    $expect[] = array(
      '19970902',
      '19970904',
      '19970906',
    );

    $tests[] = array(
      'INTERVAL' => 92,
    );
    $expect[] = array(
      '19970902',
      '19971203',
      '19980305',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
    );
    $expect[] = array(
      '19980101',
      '19980102',
      '19980103',
    );

    // This is testing that INTERVAL is respected in the presence of a BYMONTH
    // filter which skips some months.
    $tests[] = array(
      'BYMONTH' => array(12),
      'INTERVAL' => 17,
    );
    $expect[] = array(
      '19971213',
      '19971230',
      '19981205',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(1, 3),
    );
    $expect[] = array(
      '19970903',
      '19971001',
      '19971003',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYMONTHDAY' => array(5, 7),
    );
    $expect[] = array(
      '19980105',
      '19980107',
      '19980305',
    );

    $tests[] = array(
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19970902',
      '19970904',
      '19970909',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101',
      '19980106',
      '19980108',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101',
      '19980203',
      '19980303',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYMONTHDAY' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101',
      '19980303',
      '20010301',
    );

    $tests[] = array(
      'BYHOUR' => array(6, 18),
      'BYMINUTE' => array(15, 45),
      'BYSETPOS' => array(3, -3),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T181500Z',
      '19970903T064500Z',
      '19970903T181500Z',
    );

    $this->assertRules(
      array(
        'FREQ' => 'DAILY',
        'COUNT' => 3,
        'DTSTART' => '19970902',
      ),
      $tests,
      $expect);
  }

  public function testHourlyRecurrenceRules() {
    $tests = array();
    $expect = array();

    $tests[] = array();
    $expect[] = array(
      '19970902T090000Z',
      '19970902T100000Z',
      '19970902T110000Z',
    );

    $tests[] = array(
      'INTERVAL' => 2,
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970902T110000Z',
      '19970902T130000Z',
    );

    $tests[] = array(
      'INTERVAL' => 769,
    );
    $expect[] = array(
      '19970902T090000Z',
      '19971004T100000Z',
      '19971105T110000Z',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
    );
    $expect[] = array(
      '19980101T000000Z',
      '19980101T010000Z',
      '19980101T020000Z',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(1, 3),
    );
    $expect[] = array(
      '19970903T000000Z',
      '19970903T010000Z',
      '19970903T020000Z',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYMONTHDAY' => array(5, 7),
    );
    $expect[] = array(
      '19980105T000000Z',
      '19980105T010000Z',
      '19980105T020000Z',
    );

    $tests[] = array(
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970902T100000Z',
      '19970902T110000Z',
    );

    $tests[] = array(
      'BYMONTH' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101T000000Z',
      '19980101T010000Z',
      '19980101T020000Z',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101T000000Z',
      '19980101T010000Z',
      '19980101T020000Z',
    );

    $tests[] = array(
      'BYMONTHDAY' => array(1, 3),
      'BYMONTH' => array(1, 3),
      'BYDAY' => array('TU', 'TH'),
    );
    $expect[] = array(
      '19980101T000000Z',
      '19980101T010000Z',
      '19980101T020000Z',
    );

    $tests[] = array(
      'COUNT' => 4,
      'BYYEARDAY' => array(1, 100, 200, 365),
    );
    $expect[] = array(
      '19971231T000000Z',
      '19971231T010000Z',
      '19971231T020000Z',
      '19971231T030000Z',
    );

    $tests[] = array(
      'COUNT' => 4,
      'BYYEARDAY' => array(-365, -266, -166, -1),
    );
    $expect[] = array(
      '19971231T000000Z',
      '19971231T010000Z',
      '19971231T020000Z',
      '19971231T030000Z',
    );

    $tests[] = array(
      'COUNT' => 4,
      'BYMONTH' => array(4, 7),
      'BYYEARDAY' => array(1, 100, 200, 365),
    );
    $expect[] = array(
      '19980410T000000Z',
      '19980410T010000Z',
      '19980410T020000Z',
      '19980410T030000Z',
    );

    $tests[] = array(
      'COUNT' => 4,
      'BYMONTH' => array(4, 7),
      'BYYEARDAY' => array(-365, -266, -166, -1),
    );
    $expect[] = array(
      '19980410T000000Z',
      '19980410T010000Z',
      '19980410T020000Z',
      '19980410T030000Z',
    );

    $tests[] = array(
      'BYHOUR' => array(6, 18),
    );
    $expect[] = array(
      '19970902T180000Z',
      '19970903T060000Z',
      '19970903T180000Z',
    );

    $tests[] = array(
      'BYMINUTE' => array(15, 45),
      'BYSECOND' => array(15, 45),
      'BYSETPOS' => array(3, -3),
    );
    $expect[] = array(
      '19970902T091545Z',
      '19970902T094515Z',
      '19970902T101545Z',
    );

    $this->assertRules(
      array(
        'FREQ' => 'HOURLY',
        'COUNT' => 3,
        'DTSTART' => '19970902T090000Z',
      ),
      $tests,
      $expect);
  }

  public function testMinutelyRecurrenceRules() {
    $tests = array();
    $expect = array();

    $tests[] = array(
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970902T090100Z',
      '19970902T090200Z',
    );

    $tests[] = array(
      'INTERVAL' => 2,
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970902T090200Z',
      '19970902T090400Z',
    );

    $tests[] = array(
      'BYHOUR' => array(6, 18),
      'BYMINUTE' => array(6, 18),
      'BYSECOND' => array(6, 18),
    );
    $expect[] = array(
      '19970902T180606Z',
      '19970902T180618Z',
      '19970902T181806Z',
    );

    $tests[] = array(
      'BYSECOND' => array(15, 30, 45),
      'BYSETPOS' => array(3, -3),
    );
    $expect[] = array(
      '19970902T090015Z',
      '19970902T090045Z',
      '19970902T090115Z',
    );

    $this->assertRules(
      array(
        'FREQ' => 'MINUTELY',
        'COUNT' => 3,
        'DTSTART' => '19970902T090000Z',
      ),
      $tests,
      $expect);
  }

  public function testSecondlyRecurrenceRules() {
    $tests = array();
    $expect = array();

    $tests[] = array();
    $expect[] = array(
      '19970902T090000Z',
      '19970902T090001Z',
      '19970902T090002Z',
    );

    $tests[] = array(
      'INTERVAL' => 2,
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970902T090002Z',
      '19970902T090004Z',
    );

    $tests[] = array(
      'INTERVAL' => 90061,
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970903T100101Z',
      '19970904T110202Z',
    );

    $tests[] = array(
      'BYSECOND' => array(0),
      'BYMINUTE' => array(1),
      'DTSTART' => '20100322T120100Z',
    );
    $expect[] = array(
      '20100322T120100Z',
      '20100322T130100Z',
      '20100322T140100Z',
    );

    $this->assertRules(
      array(
        'FREQ' => 'SECONDLY',
        'COUNT' => 3,
        'DTSTART' => '19970902T090000Z',
      ),
      $tests,
      $expect);
  }

  public function testRFC5545RecurrenceRules() {
    // These tests are derived from the examples in RFC5545.
    $tests = array();
    $expect = array();

    $tests[] = array(
      'FREQ' => 'DAILY',
      'COUNT' => 10,
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970903T090000Z',
      '19970904T090000Z',
      '19970905T090000Z',
      '19970906T090000Z',
      '19970907T090000Z',
      '19970908T090000Z',
      '19970909T090000Z',
      '19970910T090000Z',
      '19970911T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'DAILY',
      'INTERVAL' => 2,
      'DTSTART' => '19970902T090000Z',
      'COUNT' => 5,
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970904T090000Z',
      '19970906T090000Z',
      '19970908T090000Z',
      '19970910T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'YEARLY',
      'BYMONTH' => array(1),
      'BYDAY' => array('MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'),
      'DTSTART' => '19970902T090000Z',
      'COUNT' => 3,
    );
    $expect[] = array(
      '19980101T090000Z',
      '19980102T090000Z',
      '19980103T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'COUNT' => 3,
      'BYDAY' => array('1FR'),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970905T090000Z',
      '19971003T090000Z',
      '19971107T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'INTERVAL' => 2,
      'COUNT' => 5,
      'BYDAY' => array('1SU', '-1SU'),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970907T090000Z',
      '19970928T090000Z',
      '19971102T090000Z',
      '19971130T090000Z',
      '19980104T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'COUNT' => 6,
      'BYDAY' => array('-2MO'),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970922T090000Z',
      '19971020T090000Z',
      '19971117T090000Z',
      '19971222T090000Z',
      '19980119T090000Z',
      '19980216T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'COUNT' => 6,
      'BYMONTHDAY' => array(-3),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970928T090000Z',
      '19971029T090000Z',
      '19971128T090000Z',
      '19971229T090000Z',
      '19980129T090000Z',
      '19980226T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'COUNT' => 5,
      'BYMONTHDAY' => array(2, 15),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970915T090000Z',
      '19971002T090000Z',
      '19971015T090000Z',
      '19971102T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'COUNT' => 5,
      'BYMONTHDAY' => array(-1, 1),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970930T090000Z',
      '19971001T090000Z',
      '19971031T090000Z',
      '19971101T090000Z',
      '19971130T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'COUNT' => 7,
      'INTERVAL' => 18,
      'BYMONTHDAY' => array(10, 11, 12, 13, 14, 15),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970910T090000Z',
      '19970911T090000Z',
      '19970912T090000Z',
      '19970913T090000Z',
      '19970914T090000Z',
      '19970915T090000Z',
      '19990310T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'COUNT' => 6,
      'INTERVAL' => 2,
      'BYDAY' => array('TU'),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970909T090000Z',
      '19970916T090000Z',
      '19970923T090000Z',
      '19970930T090000Z',
      '19971104T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'YEARLY',
      'COUNT' => 10,
      'BYMONTH' => array(6, 7),
      'DTSTART' => '19970610T090000Z',
    );
    $expect[] = array(
      '19970610T090000Z',
      '19970710T090000Z',
      '19980610T090000Z',
      '19980710T090000Z',
      '19990610T090000Z',
      '19990710T090000Z',
      '20000610T090000Z',
      '20000710T090000Z',
      '20010610T090000Z',
      '20010710T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'YEARLY',
      'COUNT' => 4,
      'INTERVAL' => 3,
      'BYYEARDAY' => array(1, 100, 200),
      'DTSTART' => '19970101T090000Z',
    );
    $expect[] = array(
      '19970101T090000Z',
      '19970410T090000Z',
      '19970719T090000Z',
      '20000101T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'YEARLY',
      'COUNT' => 3,
      'BYDAY' => array('20MO'),
      'DTSTART' => '19970519T090000Z',
    );
    $expect[] = array(
      '19970519T090000Z',
      '19980518T090000Z',
      '19990517T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'YEARLY',
      'COUNT' => 3,
      'BYWEEKNO' => array(20),
      'BYDAY' => array('MO'),
      'DTSTART' => '19970512T090000Z',
    );
    $expect[] = array(
      '19970512T090000Z',
      '19980511T090000Z',
      '19990517T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'YEARLY',
      'BYDAY' => array('TH'),
      'BYMONTH' => array(3),
      'DTSTART' => '19970313T090000Z',
      'COUNT' => 5,
    );
    $expect[] = array(
      '19970313T090000Z',
      '19970320T090000Z',
      '19970327T090000Z',
      '19980305T090000Z',
      '19980312T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'YEARLY',
      'BYDAY' => array('TH'),
      'BYMONTH' => array(6, 7, 8),
      'DTSTART' => '19970101T090000Z',
      'COUNT' => 15,
    );
    $expect[] = array(
      '19970605T090000Z',
      '19970612T090000Z',
      '19970619T090000Z',
      '19970626T090000Z',
      '19970703T090000Z',
      '19970710T090000Z',
      '19970717T090000Z',
      '19970724T090000Z',
      '19970731T090000Z',
      '19970807T090000Z',
      '19970814T090000Z',
      '19970821T090000Z',
      '19970828T090000Z',
      '19980604T090000Z',
      '19980611T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'YEARLY',
      'BYDAY' => array('FR'),
      'BYMONTHDAY' => array(13),
      'COUNT' => 4,
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19980213T090000Z',
      '19980313T090000Z',
      '19981113T090000Z',
      '19990813T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'BYDAY' => array('SA'),
      'BYMONTHDAY' => array(7, 8, 9, 10, 11, 12, 13),
      'COUNT' => 10,
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970913T090000Z',
      '19971011T090000Z',
      '19971108T090000Z',
      '19971213T090000Z',
      '19980110T090000Z',
      '19980207T090000Z',
      '19980307T090000Z',
      '19980411T090000Z',
      '19980509T090000Z',
      '19980613T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'YEARLY',
      'INTERVAL' => 4,
      'BYMONTH' => array(11),
      'BYDAY' => array('TU'),
      'BYMONTHDAY' => array(2, 3, 4, 5, 6, 7, 8),
      'COUNT' => 6,
      'DTSTART' => '19961105T090000Z',
    );
    $expect[] = array(
      '19961105T090000Z',
      '20001107T090000Z',
      '20041102T090000Z',
      '20081104T090000Z',
      '20121106T090000Z',
      '20161108T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'BYDAY' => array('TU', 'WE', 'TH'),
      'BYSETPOS' => array(3),
      'COUNT' => 3,
      'DTSTART' => '19970904T090000Z',
    );
    $expect[] = array(
      '19970904T090000Z',
      '19971007T090000Z',
      '19971106T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'MONTHLY',
      'BYDAY' => array('MO', 'TU', 'WE', 'TH', 'FR'),
      'BYSETPOS' => array(-2),
      'COUNT' => 3,
      'DTSTART' => '19970929T090000Z',
    );
    $expect[] = array(
      '19970929T090000Z',
      '19971030T090000Z',
      '19971127T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'HOURLY',
      'INTERVAL' => 3,
      'DTSTART' => '19970929T090000Z',
      'COUNT' => 3,
    );
    $expect[] = array(
      '19970929T090000Z',
      '19970929T120000Z',
      '19970929T150000Z',
    );

    $tests[] = array(
      'FREQ' => 'MINUTELY',
      'INTERVAL' => 15,
      'COUNT' => 6,
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970902T091500Z',
      '19970902T093000Z',
      '19970902T094500Z',
      '19970902T100000Z',
      '19970902T101500Z',
    );

    $tests[] = array(
      'FREQ' => 'MINUTELY',
      'INTERVAL' => 90,
      'COUNT' => 4,
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970902T103000Z',
      '19970902T120000Z',
      '19970902T133000Z',
    );

    $tests[] = array(
      'FREQ' => 'WEEKLY',
      'COUNT' => 10,
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970909T090000Z',
      '19970916T090000Z',
      '19970923T090000Z',
      '19970930T090000Z',
      '19971007T090000Z',
      '19971014T090000Z',
      '19971021T090000Z',
      '19971028T090000Z',
      '19971104T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'WEEKLY',
      'INTERVAL' => 2,
      'COUNT' => 6,
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970916T090000Z',
      '19970930T090000Z',
      '19971014T090000Z',
      '19971028T090000Z',
      '19971111T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'WEEKLY',
      'COUNT' => 10,
      'WKST' => 'SU',
      'BYDAY' => array('TU', 'TH'),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970904T090000Z',
      '19970909T090000Z',
      '19970911T090000Z',
      '19970916T090000Z',
      '19970918T090000Z',
      '19970923T090000Z',
      '19970925T090000Z',
      '19970930T090000Z',
      '19971002T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'WEEKLY',
      'INTERVAL' => 2,
      'COUNT' => 8,
      'WKST' => 'SU',
      'BYDAY' => array('TU', 'TH'),
      'DTSTART' => '19970902T090000Z',
    );
    $expect[] = array(
      '19970902T090000Z',
      '19970904T090000Z',
      '19970916T090000Z',
      '19970918T090000Z',
      '19970930T090000Z',
      '19971002T090000Z',
      '19971014T090000Z',
      '19971016T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'WEEKLY',
      'INTERVAL' => 2,
      'COUNT' => 4,
      'BYDAY' => array('TU', 'SU'),
      'WKST' => 'MO',
      'DTSTART' => '19970805T090000Z',
    );
    $expect[] = array(
      '19970805T090000Z',
      '19970810T090000Z',
      '19970819T090000Z',
      '19970824T090000Z',
    );

    $tests[] = array(
      'FREQ' => 'WEEKLY',
      'INTERVAL' => 2,
      'COUNT' => 4,
      'BYDAY' => array('TU', 'SU'),
      'WKST' => 'SU',
      'DTSTART' => '19970805T090000Z',
    );
    $expect[] = array(
      '19970805T090000Z',
      '19970817T090000Z',
      '19970819T090000Z',
      '19970831T090000Z',
    );


    $this->assertRules(array(), $tests, $expect);
  }


  private function assertRules(array $defaults, array $tests, array $expect) {
    foreach ($tests as $key => $test) {
      $options = $test + $defaults;

      $start = PhutilCalendarAbsoluteDateTime::newFromISO8601(
        $options['DTSTART']);

      $rrule = id(new PhutilCalendarRecurrenceRule())
        ->setStartDateTime($start)
        ->setFrequency($options['FREQ']);

      $interval = idx($options, 'INTERVAL');
      if ($interval) {
        $rrule->setInterval($interval);
      }

      $by_day = idx($options, 'BYDAY');
      if ($by_day) {
        $rrule->setByDay($by_day);
      }

      $by_month = idx($options, 'BYMONTH');
      if ($by_month) {
        $rrule->setByMonth($by_month);
      }

      $by_monthday = idx($options, 'BYMONTHDAY');
      if ($by_monthday) {
        $rrule->setByMonthDay($by_monthday);
      }

      $by_yearday = idx($options, 'BYYEARDAY');
      if ($by_yearday) {
        $rrule->setByYearDay($by_yearday);
      }

      $by_weekno = idx($options, 'BYWEEKNO');
      if ($by_weekno) {
        $rrule->setByWeekNumber($by_weekno);
      }

      $by_hour = idx($options, 'BYHOUR');
      if ($by_hour) {
        $rrule->setByHour($by_hour);
      }

      $by_minute = idx($options, 'BYMINUTE');
      if ($by_minute) {
        $rrule->setByMinute($by_minute);
      }

      $by_second = idx($options, 'BYSECOND');
      if ($by_second) {
        $rrule->setBySecond($by_second);
      }

      $by_setpos = idx($options, 'BYSETPOS');
      if ($by_setpos) {
        $rrule->setBySetPosition($by_setpos);
      }

      $week_start = idx($options, 'WKST');
      if ($week_start) {
        $rrule->setWeekStart($week_start);
      }

      $set = id(new PhutilCalendarRecurrenceSet())
        ->addSource($rrule);

      $result = $set->getEventsBetween(null, null, $options['COUNT']);

      $this->assertEqual(
        $expect[$key],
        mpull($result, 'getISO8601'));
    }
  }


}
