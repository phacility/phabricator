<?php

final class PhortuneCurrencyTestCase extends PhabricatorTestCase {

  public function testCurrencyFormatForDisplay() {
    $map = array(
      0 => '$0.00 USD',
      1 => '$0.01 USD',
      100 => '$1.00 USD',
      -123 => '$-1.23 USD',
      5000000 => '$50000.00 USD',
    );

    foreach ($map as $input => $expect) {
      $this->assertEqual(
        $expect,
        PhortuneCurrency::newFromUSDCents($input)->formatForDisplay(),
        "formatForDisplay({$input})");
    }
  }


  public function testCurrencyFormatBareValue() {

    // NOTE: The PayPal API depends on the behavior of the bare value format!

    $map = array(
      0 => '0.00',
      1 => '0.01',
      100 => '1.00',
      -123 => '-1.23',
      5000000 => '50000.00',
    );

    foreach ($map as $input => $expect) {
      $this->assertEqual(
        $expect,
        PhortuneCurrency::newFromUSDCents($input)->formatBareValue(),
        "formatBareValue({$input})");
    }
  }

  public function testCurrencyFromUserInput() {

    $map = array(
      '1.00' => 100,
      '1.00 USD' => 100,
      '$1.00' => 100,
      '$1.00 USD' => 100,
      '-$1.00 USD' => -100,
      '$-1.00 USD' => -100,
      '1' => 100,
      '.99' => 99,
      '$.99' => 99,
      '-$.99' => -99,
      '$-.99' => -99,
      '$.99 USD' => 99,
    );

    $user = new PhabricatorUser();

    foreach ($map as $input => $expect) {
      $this->assertEqual(
        $expect,
        PhortuneCurrency::newFromUserInput($user, $input)->getValue(),
        "newFromUserInput({$input})->getValue()");
    }
  }

  public function testInvalidCurrencyFromUserInput() {
    $map = array(
      '--1',
      '$$1',
      '1 JPY',
      'buck fiddy',
      '1.2.3',
      '1 dollar',
    );

    $user = new PhabricatorUser();

    foreach ($map as $input) {
      $caught = null;
      try {
        PhortuneCurrency::newFromUserInput($user, $input);
      } catch (Exception $ex) {
        $caught = $ex;
      }
      $this->assertEqual(true, ($caught instanceof Exception), "{$input}");
    }
  }

}
