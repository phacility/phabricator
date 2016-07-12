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

}
