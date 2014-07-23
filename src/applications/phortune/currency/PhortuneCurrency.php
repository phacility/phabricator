<?php

final class PhortuneCurrency extends Phobject {

  private $value;
  private $currency;

  private function __construct() {
    // Intentionally private.
  }

  public static function newFromUserInput(PhabricatorUser $user, $string) {
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

    $currency = idx($matches, 2, 'USD');
    if ($currency) {
      switch ($currency) {
        case 'USD':
          break;
        default:
          throw new Exception("Unsupported currency '{$currency}'!");
      }
    }

    $obj = new PhortuneCurrency();

    $obj->value = $value;
    $obj->currency = $currency;

    return $obj;
  }

  public static function newFromList(array $list) {
    assert_instances_of($list, 'PhortuneCurrency');

    $total = 0;
    foreach ($list as $item) {
      // TODO: This should check for integer overflows, etc.
      $total += $item->getValue();
    }

    return PhortuneCurrency::newFromUSDCents($total);
  }

  public static function newFromUSDCents($cents) {
    if (!is_int($cents)) {
      throw new Exception(
        pht('USDCents value "%s" is not an integer!', $cents));
    }

    $obj = new PhortuneCurrency();

    $obj->value = $cents;
    $obj->currency = 'USD';

    return $obj;
  }

  public function formatForDisplay() {
    $bare = $this->formatBareValue();
    return '$'.$bare.' USD';
  }

  public function formatBareValue() {
    switch ($this->currency) {
      case 'USD':
        return sprintf('%.02f', $this->value / 100);
      default:
        throw new Exception('Unsupported currency!');

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

}
