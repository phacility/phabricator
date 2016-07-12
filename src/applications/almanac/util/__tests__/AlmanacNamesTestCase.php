<?php

final class AlmanacNamesTestCase extends PhabricatorTestCase {

  public function testServiceOrDeviceNames() {
    $map = array(
      '' => false,
      'a' => false,
      'ab' => false,
      '...' => false,
      'ab.' => false,
      '.ab' => false,
      'A-B' => false,
      'A!B' => false,
      'A.B' => false,
      'a..b' => false,
      '1.2' => false,
      '127.0.0.1' => false,
      '1.b' => false,
      'a.1' => false,
      'a.1.b' => false,
      '-.a' => false,
      '-a.b' => false,
      'a-.b' => false,
      'a.-' => false,
      'a.-b' => false,
      'a.b-' => false,
      '-.-' => false,
      'a--b' => false,

      'abc' => true,
      'a.b' => true,
      'db.phacility.instance' => true,
      'web002.useast.example.com' => true,
      'master.example-corp.com' => true,

      // Maximum length is 100.
      str_repeat('a', 100) => true,
      str_repeat('a', 101) => false,
    );

    foreach ($map as $input => $expect) {
      $caught = null;
      try {
        AlmanacNames::validateName($input);
      } catch (Exception $ex) {
        $caught = $ex;
      }
      $this->assertEqual(
        $expect,
        !($caught instanceof Exception),
        $input);
    }
  }
}
