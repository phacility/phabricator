<?php

final class HeraldRepetitionPolicyConfig extends Phobject {

  const FIRST   = 'first';  // only execute the first time (no repeating)
  const EVERY   = 'every';  // repeat every time

  private static $policyIntMap = array(
    self::FIRST   => 0,
    self::EVERY   => 1,
  );

  public static function getMap() {
    return array(
      self::EVERY   => pht('every time'),
      self::FIRST   => pht('only the first time'),
    );
  }

  public static function toInt($str) {
    return idx(self::$policyIntMap, $str, self::$policyIntMap[self::EVERY]);
  }

  public static function toString($int) {
    return idx(array_flip(self::$policyIntMap), $int, self::EVERY);
  }

}
