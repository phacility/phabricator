<?php

final class PhortuneUtil {

  public static function parseCurrency($string) {
    $string = trim($string);
    if (!preg_match('/^[-]?[$]?\d+([.]\d{0,2})?$/', $string)) {
      throw new Exception("Invalid currency format ('{$string}').");
    }

    $value = str_replace('$', '', $string);
    $value = (float)$value;
    $value = (int)round(100 * $value);

    return $value;
  }

  public static function formatCurrency($price_in_cents) {
    if (!preg_match('/^[-]?\d+$/', $price_in_cents)) {
      throw new Exception("Invalid price in cents ('{$price_in_cents}').");
    }

    $negative = ($price_in_cents < 0);
    $price_in_cents = abs($price_in_cents);
    $display_value = sprintf('$%.02f', $price_in_cents / 100);

    if ($negative) {
      $display_value = '-'.$display_value;
    }

    return $display_value;
  }

}
