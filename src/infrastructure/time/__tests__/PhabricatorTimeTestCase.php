<?php

final class PhabricatorTimeTestCase extends PhabricatorTestCase {

  public function testPhabricatorTimeStack() {
    $t = 1370202281;
    $time = PhabricatorTime::pushTime($t, 'UTC');

    $this->assertTrue(PhabricatorTime::getNow() === $t);

    unset($time);

    $this->assertFalse(PhabricatorTime::getNow() === $t);
  }

  public function testParseLocalTime() {
    $u = new PhabricatorUser();
    $u->setTimezoneIdentifier('UTC');

    $v = new PhabricatorUser();
    $v->setTimezoneIdentifier('America/Los_Angeles');

    $t = 1370202281; // 2013-06-02 12:44:41 -0700
    $time = PhabricatorTime::pushTime($t, 'America/Los_Angeles');

    $this->assertEqual(
      $t,
      PhabricatorTime::parseLocalTime('now', $u));
    $this->assertEqual(
      $t,
      PhabricatorTime::parseLocalTime('now', $v));

    $this->assertEqual(
      $t,
      PhabricatorTime::parseLocalTime('2013-06-02 12:44:41 -0700', $u));
    $this->assertEqual(
      $t,
      PhabricatorTime::parseLocalTime('2013-06-02 12:44:41 -0700', $v));

    $this->assertEqual(
      $t,
      PhabricatorTime::parseLocalTime('2013-06-02 12:44:41 PDT', $u));
    $this->assertEqual(
      $t,
      PhabricatorTime::parseLocalTime('2013-06-02 12:44:41 PDT', $v));

    $this->assertEqual(
      $t,
      PhabricatorTime::parseLocalTime('2013-06-02 19:44:41', $u));
    $this->assertEqual(
      $t,
      PhabricatorTime::parseLocalTime('2013-06-02 12:44:41', $v));

    $this->assertEqual(
      $t + 3600,
      PhabricatorTime::parseLocalTime('+1 hour', $u));
    $this->assertEqual(
      $t + 3600,
      PhabricatorTime::parseLocalTime('+1 hour', $v));

    unset($time);

    $t = 1370239200; // 2013-06-02 23:00:00 -0700
    $time = PhabricatorTime::pushTime($t, 'America/Los_Angeles');

    // For the UTC user, midnight was 6 hours ago because it's early in the
    // morning for htem. For the PDT user, midnight was 23 hours ago.
    $this->assertEqual(
      $t + (-6 * 3600) + 60,
      PhabricatorTime::parseLocalTime('12:01:00 AM', $u));
    $this->assertEqual(
      $t + (-23 * 3600) + 60,
      PhabricatorTime::parseLocalTime('12:01:00 AM', $v));

    unset($time);
  }

}
