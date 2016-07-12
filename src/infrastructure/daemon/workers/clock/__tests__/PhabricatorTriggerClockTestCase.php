<?php

final class PhabricatorTriggerClockTestCase extends PhabricatorTestCase {

  public function testOneTimeTriggerClock() {
    $now = PhabricatorTime::getNow();

    $clock = new PhabricatorOneTimeTriggerClock(
      array(
        'epoch' => $now,
      ));

    $this->assertEqual(
      $now,
      $clock->getNextEventEpoch(null, false),
      pht('Should trigger at specified epoch.'));

    $this->assertEqual(
      null,
      $clock->getNextEventEpoch(1, false),
      pht('Should trigger only once.'));
  }

  public function testNeverTriggerClock() {
    $clock = new PhabricatorNeverTriggerClock(array());

    $this->assertEqual(
      null,
      $clock->getNextEventEpoch(null, false),
      pht('Should never trigger.'));
  }

  public function testDailyRoutineTriggerClockDaylightSavings() {
    // These dates are selected to cross daylight savings in PST; they should
    // be unaffected.
    $start = strtotime('2015-03-05 16:17:18 UTC');

    $clock = new PhabricatorDailyRoutineTriggerClock(
      array(
        'start' => $start,
      ));

    $expect_list = array(
      '2015-03-06 16:17:18',
      '2015-03-07 16:17:18',
      '2015-03-08 16:17:18',
      '2015-03-09 16:17:18',
      '2015-03-10 16:17:18',
    );

    $this->expectClock($clock, $expect_list, pht('Daily Routine (PST)'));
  }

  public function testDailyRoutineTriggerClockLeapSecond() {
    // These dates cross the leap second on June 30, 2012. There has never
    // been a negative leap second, so we can't test that yet.
    $start = strtotime('2012-06-28 23:59:59 UTC');

    $clock = new PhabricatorDailyRoutineTriggerClock(
      array(
        'start' => $start,
      ));

    $expect_list = array(
      '2012-06-29 23:59:59',
      '2012-06-30 23:59:59',
      '2012-07-01 23:59:59',
      '2012-07-02 23:59:59',
    );

    $this->expectClock($clock, $expect_list, pht('Daily Routine (Leap)'));
  }


  public function testCDailyRoutineTriggerClockAdjustTimeOfDay() {
    // In this case, we're going to update the time of day on the clock and
    // make sure it keeps track of the date but adjusts the time.
    $start = strtotime('2015-01-15 6:07:08 UTC');

    $clock = new PhabricatorDailyRoutineTriggerClock(
      array(
        'start' => $start,
      ));

    $expect_list = array(
      '2015-01-16 6:07:08',
      '2015-01-17 6:07:08',
      '2015-01-18 6:07:08',
    );

    $last_epoch = $this->expectClock(
      $clock,
      $expect_list,
      pht('Daily Routine (Pre-Adjust)'));

    // Now, change the time of day.
    $new_start = strtotime('2015-01-08 1:23:45 UTC');

    $clock = new PhabricatorDailyRoutineTriggerClock(
      array(
        'start' => $new_start,
      ));

    $expect_list = array(
      '2015-01-19 1:23:45',
      '2015-01-20 1:23:45',
      '2015-01-21 1:23:45',
    );

    $this->expectClock(
      $clock,
      $expect_list,
      pht('Daily Routine (Post-Adjust)'),
      $last_epoch);
  }

  public function testSubscriptionTriggerClock() {
    $start = strtotime('2014-01-31 2:34:56 UTC');

    $clock = new PhabricatorSubscriptionTriggerClock(
      array(
        'start' => $start,
      ));

    $expect_list = array(
      // This should be moved to the 28th of February.
      '2014-02-28 2:34:56',

      // In March, which has 31 days, it should move back to the 31st.
      '2014-03-31 2:34:56',

      // On months with only 30 days, it should occur on the 30th.
      '2014-04-30 2:34:56',
      '2014-05-31 2:34:56',
      '2014-06-30 2:34:56',
      '2014-07-31 2:34:56',
      '2014-08-31 2:34:56',
      '2014-09-30 2:34:56',
      '2014-10-31 2:34:56',
      '2014-11-30 2:34:56',
      '2014-12-31 2:34:56',

      // After billing on Dec 31 2014, it should wrap around to Jan 31 2015.
      '2015-01-31 2:34:56',
      '2015-02-28 2:34:56',
      '2015-03-31 2:34:56',
      '2015-04-30 2:34:56',
      '2015-05-31 2:34:56',
      '2015-06-30 2:34:56',
      '2015-07-31 2:34:56',
      '2015-08-31 2:34:56',
      '2015-09-30 2:34:56',
      '2015-10-31 2:34:56',
      '2015-11-30 2:34:56',
      '2015-12-31 2:34:56',
      '2016-01-31 2:34:56',

      // Finally, this should bill on leap day in 2016.
      '2016-02-29 2:34:56',
      '2016-03-31 2:34:56',
    );

    $this->expectClock($clock, $expect_list, pht('Billing Cycle'));
  }

  private function expectClock(
    PhabricatorTriggerClock $clock,
    array $expect_list,
    $clock_name,
    $last_epoch = null) {

    foreach ($expect_list as $cycle => $expect) {
      $next_epoch = $clock->getNextEventEpoch(
        $last_epoch,
        ($last_epoch !== null));

      $this->assertEqual(
        $expect,
        id(new DateTime('@'.$next_epoch))->format('Y-m-d G:i:s'),
        pht('%s (%s)', $clock_name, $cycle));

      $last_epoch = $next_epoch;
    }

    return $last_epoch;
  }

}
