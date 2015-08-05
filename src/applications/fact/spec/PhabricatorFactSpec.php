<?php

abstract class PhabricatorFactSpec extends Phobject {

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
    return pht(
      'Fact (%s)',
      $this->getType());
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
