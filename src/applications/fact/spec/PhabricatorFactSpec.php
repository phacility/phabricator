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

abstract class PhabricatorFactSpec {

  const UNIT_COUNT = 'unit-count';
  const UNIT_EPOCH = 'unit-epoch';

  public static function newSpecsForFactTypes(
    array $engines,
    array $fact_types) {
    assert_instances_of($engines, 'PhabricatorFactEngine');

    $map = array();
    foreach ($engines as $engine) {
      $specs = $engine->getFactSpecs($fact_types);
      $specs = mpull($specs, null, 'getType');
      $map += $specs;
    }

    foreach ($fact_types as $type) {
      if (empty($map[$type])) {
        $map[$type] = new PhabricatorFactSimpleSpec($type);
      }
    }

    return $map;
  }

  abstract public function getType();

  public function getUnit() {
    return null;
  }

  public function getName() {
    $type = $this->getType();
    return "Fact ({$type})";
  }

  public function formatValueForDisplay(PhabricatorUser $user, $value) {
    $unit = $this->getUnit();
    switch ($unit) {
      case self::UNIT_COUNT:
        return number_format($value);
      case self::UNIT_EPOCH:
        return phabricator_datetime($value, $user);
      default:
        return $value;
    }
  }

}
