<?php

final class PhabricatorConfigJSON extends Phobject {
  /**
   * Properly format a JSON value.
   *
   * @param wild Any value, but should be a raw value, not a string of JSON.
   * @return string
   */
  public static function prettyPrintJSON($value) {
    // Check not only that it's an array, but that it's an "unnatural" array
    // meaning that the keys aren't 0 -> size_of_array.
    if (is_array($value) && array_keys($value) != range(0, count($value) - 1)) {
      $result = id(new PhutilJSON())->encodeFormatted($value);
    } else {
      $result = json_encode($value);
    }

    // For readability, unescape forward slashes. These are normally escaped
    // to prevent the string "</script>" from appearing in a JSON literal,
    // but it's irrelevant here and makes reading paths more difficult than
    // necessary.
    $result = str_replace('\\/', '/', $result);
    return $result;

  }
}
