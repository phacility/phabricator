<?php

final class PhabricatorTOTPAuthFactorTestCase extends PhabricatorTestCase {

  public function testTOTPCodeGeneration() {
    $tests = array(
      array(
        'AAAABBBBCCCCDDDD',
        46620383,
        '724492',
      ),
      array(
        'AAAABBBBCCCCDDDD',
        46620390,
        '935803',
      ),
      array(
        'Z3RFWEFJN233R23P',
        46620398,
        '273030',
      ),

      // This is testing the case where the code has leading zeroes.
      array(
        'Z3RFWEFJN233R23W',
        46620399,
        '072346',
      ),
    );

    foreach ($tests as $test) {
      list($key, $time, $code) = $test;
      $this->assertEqual(
        $code,
        PhabricatorTOTPAuthFactor::getTOTPCode(
          new PhutilOpaqueEnvelope($key),
          $time));
    }

  }

}
