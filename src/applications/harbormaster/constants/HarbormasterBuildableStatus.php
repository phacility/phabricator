<?php

final class HarbormasterBuildableStatus extends Phobject {

  const STATUS_PREPARING = 'preparing';
  const STATUS_BUILDING = 'building';
  const STATUS_PASSED = 'passed';
  const STATUS_FAILED = 'failed';

  private $key;
  private $properties;

  public function __construct($key, array $properties) {
    $this->key = $key;
    $this->properties = $properties;
  }

  public static function newBuildableStatusObject($status) {
    $spec = self::getSpecification($status);
    return new self($status, $spec);
  }

  private function getProperty($key) {
    if (!array_key_exists($key, $this->properties)) {
      throw new Exception(
        pht(
          'Attempting to access unknown buildable status property ("%s").',
          $key));
    }

    return $this->properties[$key];
  }

  public function getIcon() {
    return $this->getProperty('icon');
  }

  public function getDisplayName() {
    return $this->getProperty('name');
  }

  public function getActionName() {
    return $this->getProperty('name.action');
  }

  public function getColor() {
    return $this->getProperty('color');
  }

  public function isPreparing() {
    return ($this->key === self::STATUS_PREPARING);
  }

  public function isBuilding() {
    return ($this->key === self::STATUS_BUILDING);
  }

  public function isFailed() {
    return ($this->key === self::STATUS_FAILED);
  }

  public static function getOptionMap() {
    return ipull(self::getSpecifications(), 'name');
  }

  private static function getSpecifications() {
    return array(
      self::STATUS_PREPARING => array(
        'name' => pht('Preparing'),
        'color' => 'blue',
        'icon' => 'fa-hourglass-o',
        'name.action' => pht('Build Preparing'),
      ),
      self::STATUS_BUILDING => array(
        'name' => pht('Building'),
        'color' => 'blue',
        'icon' => 'fa-chevron-circle-right',
        'name.action' => pht('Build Started'),
      ),
      self::STATUS_PASSED => array(
        'name' => pht('Passed'),
        'color' => 'green',
        'icon' => 'fa-check-circle',
        'name.action' => pht('Build Passed'),
      ),
      self::STATUS_FAILED => array(
        'name' => pht('Failed'),
        'color' => 'red',
        'icon' => 'fa-times-circle',
        'name.action' => pht('Build Failed'),
      ),
    );
  }

  private static function getSpecification($status) {
    $map = self::getSpecifications();
    if (isset($map[$status])) {
      return $map[$status];
    }

    return array(
      'name' => pht('Unknown ("%s")', $status),
      'icon' => 'fa-question-circle',
      'color' => 'bluegrey',
      'name.action' => pht('Build Status'),
    );
  }

}
