<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
      ->setName('International Testing Day')
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
        "{$n} business days since '{$date}'");
    }
  }

}
