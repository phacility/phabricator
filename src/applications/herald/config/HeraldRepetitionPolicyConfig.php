<?php

final class HeraldRepetitionPolicyConfig {
  const FIRST   = 'first';  // only execute the first time (no repeating)
  const EVERY   = 'every';  // repeat every time

  private static $policyIntMap = array(
    self::FIRST   => 0,
    self::EVERY   => 1,
  );

  private static $policyMap = array(
    self::FIRST   => 'only the first time',
    self::EVERY   => 'every time',
  );

  public static function getMap() {
    return self::$policyMap;
  }

  public static function getMapForContentType($type) {
    switch ($type) {
      case HeraldContentTypeConfig::CONTENT_TYPE_DIFFERENTIAL:
        return array_select_keys(
          self::$policyMap,
          array(
            self::EVERY,
            self::FIRST,
        ));

      case HeraldContentTypeConfig::CONTENT_TYPE_COMMIT:
      case HeraldContentTypeConfig::CONTENT_TYPE_MERGE:
      case HeraldContentTypeConfig::CONTENT_TYPE_OWNERS:
        return array();

      default:
        throw new Exception("Unknown content type '{$type}'.");
    }
  }

  public static function toInt($str) {
    return idx(self::$policyIntMap, $str, self::$policyIntMap[self::EVERY]);
  }

  public static function toString($int) {
    return idx(array_flip(self::$policyIntMap), $int, self::EVERY);
  }
}
