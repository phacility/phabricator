<?php

final class CalendarTimeUtilTestCase extends PhabricatorTestCase {

  public function testTimestampsAtMidnight() {
    $u = new PhabricatorUser();
    $u->setTimezoneIdentifier('America/Los_Angeles');
    $days = $this->getAllDays();
    foreach ($days as $day) {
      $data = CalendarTimeUtil::getCalendarWidgetTimestamps(
        $u,
        $day);

      $this->assertEqual(
        '000000',
        $data['epoch_stamps'][0]->format('His'));
    }
  }

  public function testTimestampsStartDay() {
    $u = new PhabricatorUser();
    $u->setTimezoneIdentifier('America/Los_Angeles');
    $days = $this->getAllDays();
    foreach ($days as $day) {
      $data = CalendarTimeUtil::getTimestamps(
        $u,
        $day,
        1);

      $this->assertEqual(
        $day,
        $data['epoch_stamps'][0]->format('l'));
    }

    $t = 1370202281; // 2013-06-02 12:44:41 -0700 -- a Sunday
    $time = PhabricatorTime::pushTime($t, 'America/Los_Angeles');
    foreach ($days as $day) {
      $data = CalendarTimeUtil::getTimestamps(
        $u,
        $day,
        1);

      $this->assertEqual(
        $day,
        $data['epoch_stamps'][0]->format('l'));
    }
    unset($time);
  }

  private function getAllDays() {
    return array(
      'Sunday',
      'Monday',
      'Tuesday',
      'Wednesday',
      'Thursday',
      'Friday',
      'Saturday',
    );
  }

}
