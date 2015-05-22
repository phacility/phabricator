<?php

final class PhortuneCurrency extends Phobject {

  private $value;
  private $currency;

  private function __construct() {
    // Intentionally private.
  }

  public static function getDefaultCurrency() {
    return 'USD';
  }

  public static function newEmptyCurrency() {
    return self::newFromString('0.00 USD');
  }

  public static function newFromUserInput(PhabricatorUser $user, $string) {
    // Eventually, this might select a default currency based on user settings.
    return self::newFromString($string, self::getDefaultCurrency());
  }

  public static function newFromString($string, $default = null) {
    $matches = null;
    $ok = preg_match(
      '/^([-$]*(?:\d+)?(?:[.]\d{0,2})?)(?:\s+([A-Z]+))?$/',
      trim($string),
      $matches);

    if (!$ok) {
      self::throwFormatException($string);
    }

    $value = $matches[1];

    if (substr_count($value, '-') > 1) {
      self::throwFormatException($string);
    }

    if (substr_count($value, '$') > 1) {
      self::throwFormatException($string);
    }

    $value = str_replace('$', '', $value);
    $value = (float)$value;
    $value = (int)round(100 * $value);

    $currency = idx($matches, 2, $default);
    switch ($currency) {
      case 'USD':
        break;
      default:
        throw new Exception(pht("Unsupported currency '%s'!", $currency));
    }

    return self::newFromValueAndCurrency($value, $currency);
  }

  public static function newFromValueAndCurrency($value, $currency) {
    $obj = new PhortuneCurrency();

    $obj->value = $value;
    $obj->currency = $currency;

    return $obj;
  }

  public static function newFromList(array $list) {
    assert_instances_of($list, __CLASS__);

    if (!$list) {
      return self::newEmptyCurrency();
    }

    $total = null;
    foreach ($list as $item) {
      if ($total === null) {
        $total = $item;
      } else {
        $total = $total->add($item);
      }
    }

    return $total;
  }

  public function formatForDisplay() {
    $bare = $this->formatBareValue();
    return '$'.$bare.' '.$this->currency;
  }

  public function serializeForStorage() {
    return $this->formatBareValue().' '.$this->currency;
  }

  public function formatBareValue() {
    switch ($this->currency) {
      case 'USD':
        return sprintf('%.02f', $this->value / 100);
      default:
        throw new Exception(
          pht('Unsupported currency ("%s")!', $this->currency));
    }
  }

  public function getValue() {
    return $this->value;
  }

  public function getCurrency() {
    return $this->currency;
  }

  public function getValueInUSDCents() {
    if ($this->currency !== 'USD') {
      throw new Exception(pht('Unexpected currency!'));
    }
    return $this->value;
  }

  private static function throwFormatException($string) {
    throw new Exception(pht("Invalid currency format ('%s').", $string));
  }

  private function throwUnlikeCurrenciesException(PhortuneCurrency $other) {
    throw new Exception(
      pht(
        'Trying to operate on unlike currencies ("%s" and "%s")!',
        $this->currency,
        $other->currency));
  }

  public function add(PhortuneCurrency $other) {
    if ($this->currency !== $other->currency) {
      $this->throwUnlikeCurrenciesException($other);
    }

    $currency = new PhortuneCurrency();

    // TODO: This should check for integer overflows, etc.
    $currency->value = $this->value + $other->value;
    $currency->currency = $this->currency;

    return $currency;
  }

  public function subtract(PhortuneCurrency $other) {
    if ($this->currency !== $other->currency) {
      $this->throwUnlikeCurrenciesException($other);
    }

    $currency = new PhortuneCurrency();

    // TODO: This should check for integer overflows, etc.
    $currency->value = $this->value - $other->value;
    $currency->currency = $this->currency;

    return $currency;
  }

  public function isEqualTo(PhortuneCurrency $other) {
    if ($this->currency !== $other->currency) {
      $this->throwUnlikeCurrenciesException($other);
    }

    return ($this->value === $other->value);
  }

  public function negate() {
    $currency = new PhortuneCurrency();
    $currency->value = -$this->value;
    $currency->currency = $this->currency;
    return $currency;
  }

  public function isPositive() {
    return ($this->value > 0);
  }

  public function isGreaterThan(PhortuneCurrency $other) {
    if ($this->currency !== $other->currency) {
      $this->throwUnlikeCurrenciesException($other);
    }
    return $this->value > $other->value;
  }

  /**
   * Assert that a currency value lies within a range.
   *
   * Throws if the value is not between the minimum and maximum, inclusive.
   *
   * In particular, currency values can be negative (to represent a debt or
   * credit), so checking against zero may be useful to make sure a value
   * has the expected sign.
   *
   * @param string|null Currency string, or null to skip check.
   * @param string|null Currency string, or null to skip check.
   * @return this
   */
  public function assertInRange($minimum, $maximum) {
    if ($minimum !== null && $maximum !== null) {
      $min = self::newFromString($minimum);
      $max = self::newFromString($maximum);
      if ($min->value > $max->value) {
        throw new Exception(
          pht(
            'Range (%s - %s) is not valid!',
            $min->formatForDisplay(),
            $max->formatForDisplay()));
      }
    }

    if ($minimum !== null) {
      $min = self::newFromString($minimum);
      if ($min->value > $this->value) {
        throw new Exception(
          pht(
            'Minimum allowed amount is %s.',
            $min->formatForDisplay()));
      }
    }

    if ($maximum !== null) {
      $max = self::newFromString($maximum);
      if ($max->value < $this->value) {
        throw new Exception(
          pht(
            'Maximum allowed amount is %s.',
            $max->formatForDisplay()));
      }
    }

    return $this;
  }


}
