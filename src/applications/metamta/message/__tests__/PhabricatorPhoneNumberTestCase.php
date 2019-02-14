<?php

final class PhabricatorPhoneNumberTestCase
  extends PhabricatorTestCase {

  public function testNumberNormalization() {
    $map = array(
      '+15555555555' => '+15555555555',
      '+1 (555) 555-5555' => '+15555555555',
      '(555) 555-5555' => '+15555555555',

      '' => false,
      '1-800-CALL-SAUL' => false,
    );

    foreach ($map as $input => $expect) {
      $caught = null;
      try {
        $actual = id(new PhabricatorPhoneNumber($input))
          ->toE164();
      } catch (Exception $ex) {
        $caught = $ex;
      }

      $this->assertEqual(
        (bool)$caught,
        ($expect === false),
        pht('Exception raised by: %s', $input));

      if ($expect !== false) {
        $this->assertEqual($expect, $actual, pht('E164 of: %s', $input));
      }
    }

  }

}
