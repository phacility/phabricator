<?php

final class PhabricatorConfigJSON extends Phobject {
  /**
   * Properly format a JSON value.
   *
   * @param wild Any value, but should be a raw value, not a string of JSON.
   * @return string
   */
  public static function prettyPrintJSON($value) {
    // If the value is an array with keys "0, 1, 2, ..." then we want to
    // show it as a list.
    // If the value is an array with other keys, we want to show it as an
    // object.
    // Otherwise, just use the default encoder.

    $type = null;
    if (is_array($value)) {
      $list_keys = range(0, count($value) - 1);
      $actual_keys = array_keys($value);

      if ($actual_keys === $list_keys) {
        $type = 'list';
      } else {
        $type = 'object';
      }
    }

    switch ($type) {
      case 'list':
        $result = id(new PhutilJSON())->encodeAsList($value);
        break;
      case 'object':
        $result = id(new PhutilJSON())->encodeFormatted($value);
        break;
      default:
        $result = json_encode($value);
        break;
    }

    // For readability, unescape forward slashes. These are normally escaped
    // to prevent the string "</script>" from appearing in a JSON literal,
    // but it's irrelevant here and makes reading paths more difficult than
    // necessary.
    $result = str_replace('\\/', '/', $result);
    return $result;

  }
}
