<?php

final class PhortuneCurrencyTestCase extends PhabricatorTestCase {

  public function testCurrencyFormatForDisplay() {
    $map = array(
      '0' => '$0.00 USD',
      '.01' => '$0.01 USD',
      '1.00' => '$1.00 USD',
      '-1.23' => '$-1.23 USD',
      '50000.00' => '$50000.00 USD',
    );

    foreach ($map as $input => $expect) {
      $this->assertEqual(
        $expect,
        PhortuneCurrency::newFromString($input, 'USD')->formatForDisplay(),
        "newFromString({$input})->formatForDisplay()");
    }
  }

  public function testCurrencyFormatBareValue() {

    // NOTE: The PayPal API depends on the behavior of the bare value format!

    $map = array(
      '0' => '0.00',
      '.01' => '0.01',
      '1.00' => '1.00',
      '-1.23' => '-1.23',
      '50000.00' => '50000.00',
    );

    foreach ($map as $input => $expect) {
      $this->assertEqual(
        $expect,
        PhortuneCurrency::newFromString($input, 'USD')->formatBareValue(),
        "newFromString({$input})->formatBareValue()");
    }
  }

  public function testCurrencyFromString() {

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

    foreach ($map as $input => $expect) {
      $this->assertEqual(
        $expect,
        PhortuneCurrency::newFromString($input, 'USD')->getValue(),
        "newFromString({$input})->getValue()");
    }
  }

  public function testInvalidCurrencyFromString() {
    $map = array(
      '--1',
      '$$1',
      '1 JPY',
      'buck fiddy',
      '1.2.3',
      '1 dollar',
    );

    foreach ($map as $input) {
      $caught = null;
      try {
        PhortuneCurrency::newFromString($input, 'USD');
      } catch (Exception $ex) {
        $caught = $ex;
      }
      $this->assertTrue($caught instanceof Exception, "{$input}");
    }
  }

  public function testCurrencyRanges() {
    $value = PhortuneCurrency::newFromString('3.00 USD');

    $value->assertInRange('2.00 USD', '4.00 USD');
    $value->assertInRange('2.00 USD', null);
    $value->assertInRange(null, '4.00 USD');
    $value->assertInRange(null, null);

    $caught = null;
    try {
      $value->assertInRange('4.00 USD', null);
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);

    $caught = null;
    try {
      $value->assertInRange(null, '2.00 USD');
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);

    $caught = null;
    try {
      // Minimum and maximum are reversed here.
      $value->assertInRange('4.00 USD', '2.00 USD');
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);

    $credit = PhortuneCurrency::newFromString('-3.00 USD');
    $credit->assertInRange('-4.00 USD', '-2.00 USD');
    $credit->assertInRange('-4.00 USD', null);
    $credit->assertInRange(null, '-2.00 USD');
    $credit->assertInRange(null, null);

    $caught = null;
    try {
      $credit->assertInRange('-2.00 USD', null);
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);

    $caught = null;
    try {
      $credit->assertInRange(null, '-4.00 USD');
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);
  }

  public function testAddCurrency() {
    $cases = array(
      array('0.00 USD', '0.00 USD', '$0.00 USD'),
      array('1.00 USD', '1.00 USD', '$2.00 USD'),
      array('1.23 USD', '9.77 USD', '$11.00 USD'),
    );

    foreach ($cases as $case) {
      list($l, $r, $expect) = $case;

      $l = PhortuneCurrency::newFromString($l);
      $r = PhortuneCurrency::newFromString($r);
      $sum = $l->add($r);

      $this->assertEqual($expect, $sum->formatForDisplay());
    }
  }

}
