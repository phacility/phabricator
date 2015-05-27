<?php

final class PhabricatorCalendarHolidayTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  protected function willRunTests() {
    parent::willRunTests();
    id(new PhabricatorCalendarHoliday())
      ->setDay('2012-01-02')
      ->setName(pht('International Testing Day'))
      ->save();
  }

  public function testNthBusinessDay() {
    $map = array(
      array('2011-12-30', 1, '2012-01-03'),
      array('2012-01-01', 1, '2012-01-03'),
      array('2012-01-01', 0, '2012-01-01'),
      array('2012-01-01', -1, '2011-12-30'),
      array('2012-01-04', -1, '2012-01-03'),
    );
    foreach ($map as $val) {
      list($date, $n, $expect) = $val;
      $actual = PhabricatorCalendarHoliday::getNthBusinessDay(
        strtotime($date),
        $n);
      $this->assertEqual(
        $expect,
        date('Y-m-d', $actual),
        pht("%d business days since '%s'", $n, $date));
    }
  }

}
