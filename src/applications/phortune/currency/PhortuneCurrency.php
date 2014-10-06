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
    if ($currency) {
      switch ($currency) {
        case 'USD':
          break;
        default:
          throw new Exception("Unsupported currency '{$currency}'!");
      }
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
    assert_instances_of($list, 'PhortuneCurrency');

    $total = 0;
    $currency = null;
    foreach ($list as $item) {
      if ($currency === null) {
        $currency = $item->getCurrency();
      } else if ($currency === $item->getCurrency()) {
        // Adding a value denominated in the same currency, which is
        // fine.
      } else {
        throw new Exception(
          pht('Trying to sum a list of unlike currencies.'));
      }

      // TODO: This should check for integer overflows, etc.
      $total += $item->getValue();
    }

    return PhortuneCurrency::newFromValueAndCurrency(
      $total,
      self::getDefaultCurrency());
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

  private static function throwFormatException($string) {
    throw new Exception("Invalid currency format ('{$string}').");
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
      $min = PhortuneCurrency::newFromString($minimum);
      $max = PhortuneCurrency::newFromString($maximum);
      if ($min->value > $max->value) {
        throw new Exception(
          pht(
            'Range (%s - %s) is not valid!',
            $min->formatForDisplay(),
            $max->formatForDisplay()));
      }
    }

    if ($minimum !== null) {
      $min = PhortuneCurrency::newFromString($minimum);
      if ($min->value > $this->value) {
        throw new Exception(
          pht(
            'Minimum allowed amount is %s.',
            $min->formatForDisplay()));
      }
    }

    if ($maximum !== null) {
      $max = PhortuneCurrency::newFromString($maximum);
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
