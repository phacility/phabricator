<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
