<?php

final class DifferentialUnitStatus extends Phobject {

  const UNIT_NONE       = 0;
  const UNIT_OKAY       = 1;
  const UNIT_WARN       = 2;
  const UNIT_FAIL       = 3;
  const UNIT_SKIP       = 4;
  const UNIT_AUTO_SKIP  = 6;

  private $value;

  public static function newStatusFromValue($value) {
    $status = new self();
    $status->value = $value;
    return $status;
  }

  public function getValue() {
    return $this->value;
  }

  public function getName() {
    $name = $this->getUnitStatusProperty('name');

    if ($name === null) {
      $name = pht('Unknown Unit Status ("%s")', $this->getValue());
    }

    return $name;
  }

  public function getIconIcon() {
    return $this->getUnitStatusProperty('icon.icon');
  }

  public function getIconColor() {
    return $this->getUnitStatusProperty('icon.color');
  }

  public static function getStatusMap() {
    $results = array();

    foreach (self::newUnitStatusMap() as $value => $ignored) {
      $results[$value] = self::newStatusFromValue($value);
    }

    return $results;
  }

  private function getUnitStatusProperty($key, $default = null) {
    $map = self::newUnitStatusMap();
    $properties = idx($map, $this->getValue(), array());
    return idx($properties, $key, $default);
  }

  private static function newUnitStatusMap() {
    return array(
      self::UNIT_NONE => array(
        'name' => pht('No Test Coverage'),
        'icon.icon' => 'fa-ban',
        'icon.color' => 'grey',
      ),
      self::UNIT_OKAY => array(
        'name' => pht('Tests Passed'),
        'icon.icon' => 'fa-check',
        'icon.color' => 'green',
      ),
      self::UNIT_WARN => array(
        'name' => pht('Test Warnings'),
        'icon.icon' => 'fa-exclamation-triangle',
        'icon.color' => 'yellow',
      ),
      self::UNIT_FAIL => array(
        'name' => pht('Test Failures'),
        'icon.icon' => 'fa-times',
        'icon.color' => 'red',
      ),
      self::UNIT_SKIP => array(
        'name' => pht('Tests Skipped'),
        'icon.icon' => 'fa-fast-forward',
        'icon.color' => 'blue',
      ),
      self::UNIT_AUTO_SKIP => array(
        'name' => pht('Tests Not Applicable'),
        'icon.icon' => 'fa-code',
        'icon.color' => 'grey',
      ),
    );
  }

}
