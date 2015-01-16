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

    $last_epoch = null;
    foreach ($expect_list as $cycle => $expect) {
      $next_epoch = $clock->getNextEventEpoch(
        $last_epoch,
        ($last_epoch !== null));

      $this->assertEqual(
        $expect,
        id(new DateTime('@'.$next_epoch))->format('Y-m-d g:i:s'),
        pht('Billing cycle %s.', $cycle));

      $last_epoch = $next_epoch;
    }
  }

}
