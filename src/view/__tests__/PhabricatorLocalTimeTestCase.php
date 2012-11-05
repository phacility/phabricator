<?php

final class PhabricatorLocalTimeTestCase extends PhabricatorTestCase {

  public function testLocalTimeFormatting() {
    $user = new PhabricatorUser();
    $user->setTimezoneIdentifier('America/Los_Angeles');

    $utc = new PhabricatorUser();
    $utc->setTimezoneIdentifier('UTC');

    $this->assertEqual(
      'Jan 1 2000, 12:00 AM',
      phabricator_datetime(946684800, $utc),
      'Datetime formatting');
    $this->assertEqual(
      'Jan 1 2000',
      phabricator_date(946684800, $utc),
      'Date formatting');
    $this->assertEqual(
      '12:00 AM',
      phabricator_time(946684800, $utc),
      'Time formatting');

    $this->assertEqual(
      'Dec 31 1999, 4:00 PM',
      phabricator_datetime(946684800, $user),
      'Localization');

    $this->assertEqual(
      '',
      phabricator_datetime(0, $user),
      'Missing epoch should fail gracefully');
  }

}
