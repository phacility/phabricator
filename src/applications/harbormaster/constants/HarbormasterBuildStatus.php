<?php

final class HarbormasterBuildStatus extends Phobject {

  const STATUS_INACTIVE = 'inactive';
  const STATUS_PENDING = 'pending';
  const STATUS_BUILDING = 'building';
  const STATUS_PASSED = 'passed';
  const STATUS_FAILED = 'failed';
  const STATUS_ABORTED = 'aborted';
  const STATUS_ERROR = 'error';
  const STATUS_PAUSED = 'paused';
  const STATUS_DEADLOCKED = 'deadlocked';

  const PENDING_PAUSING = 'x-pausing';
  const PENDING_RESUMING = 'x-resuming';
  const PENDING_RESTARTING = 'x-restarting';
  const PENDING_ABORTING = 'x-aborting';

  private $key;
  private $properties;

  public function __construct($key, array $properties) {
    $this->key = $key;
    $this->properties = $properties;
  }

  public static function newBuildStatusObject($status) {
    $spec = self::getBuildStatusSpec($status);
    return new self($status, $spec);
  }

  private function getProperty($key) {
    if (!array_key_exists($key, $this->properties)) {
      throw new Exception(
        pht(
          'Attempting to access unknown build status property ("%s").',
          $key));
    }

    return $this->properties[$key];
  }

  public function isBuilding() {
    return $this->getProperty('isBuilding');
  }

  public function isPaused() {
    return ($this->key === self::STATUS_PAUSED);
  }

  public function isComplete() {
    return $this->getProperty('isComplete');
  }

  public function isPassed() {
    return ($this->key === self::STATUS_PASSED);
  }

  public function isFailed() {
    return ($this->key === self::STATUS_FAILED);
  }

  public function isAborting() {
    return ($this->key === self::PENDING_ABORTING);
  }

  public function isRestarting() {
    return ($this->key === self::PENDING_RESTARTING);
  }

  public function isResuming() {
    return ($this->key === self::PENDING_RESUMING);
  }

  public function isPausing() {
    return ($this->key === self::PENDING_PAUSING);
  }

  public function isPending() {
    return ($this->key === self::STATUS_PENDING);
  }

  public function getIconIcon() {
    return $this->getProperty('icon');
  }

  public function getIconColor() {
    return $this->getProperty('color');
  }

  public function getName() {
    return $this->getProperty('name');
  }

  /**
   * Get a human readable name for a build status constant.
   *
   * @param  const  Build status constant.
   * @return string Human-readable name.
   */
  public static function getBuildStatusName($status) {
    $spec = self::getBuildStatusSpec($status);
    return $spec['name'];
  }

  public static function getBuildStatusMap() {
    $specs = self::getBuildStatusSpecMap();
    return ipull($specs, 'name');
  }

  public static function getBuildStatusIcon($status) {
    $spec = self::getBuildStatusSpec($status);
    return $spec['icon'];
  }

  public static function getBuildStatusColor($status) {
    $spec = self::getBuildStatusSpec($status);
    return $spec['color'];
  }

  public static function getBuildStatusANSIColor($status) {
    $spec = self::getBuildStatusSpec($status);
    return $spec['color.ansi'];
  }

  public static function getWaitingStatusConstants() {
    return array(
      self::STATUS_INACTIVE,
      self::STATUS_PENDING,
    );
  }

  public static function getActiveStatusConstants() {
    return array(
      self::STATUS_BUILDING,
      self::STATUS_PAUSED,
    );
  }

  public static function getIncompleteStatusConstants() {
    $map = self::getBuildStatusSpecMap();

    $constants = array();
    foreach ($map as $constant => $spec) {
      if (!$spec['isComplete']) {
        $constants[] = $constant;
      }
    }

    return $constants;
  }

  public static function getCompletedStatusConstants() {
    return array(
      self::STATUS_PASSED,
      self::STATUS_FAILED,
      self::STATUS_ABORTED,
      self::STATUS_ERROR,
      self::STATUS_DEADLOCKED,
    );
  }

  private static function getBuildStatusSpecMap() {
    return array(
      self::STATUS_INACTIVE => array(
        'name' => pht('Inactive'),
        'icon' => 'fa-circle-o',
        'color' => 'dark',
        'color.ansi' => 'yellow',
        'isBuilding' => false,
        'isComplete' => false,
      ),
      self::STATUS_PENDING => array(
        'name' => pht('Pending'),
        'icon' => 'fa-circle-o',
        'color' => 'blue',
        'color.ansi' => 'yellow',
        'isBuilding' => true,
        'isComplete' => false,
      ),
      self::STATUS_BUILDING => array(
        'name' => pht('Building'),
        'icon' => 'fa-chevron-circle-right',
        'color' => 'blue',
        'color.ansi' => 'yellow',
        'isBuilding' => true,
        'isComplete' => false,
      ),
      self::STATUS_PASSED => array(
        'name' => pht('Passed'),
        'icon' => 'fa-check-circle',
        'color' => 'green',
        'color.ansi' => 'green',
        'isBuilding' => false,
        'isComplete' => true,
      ),
      self::STATUS_FAILED => array(
        'name' => pht('Failed'),
        'icon' => 'fa-times-circle',
        'color' => 'red',
        'color.ansi' => 'red',
        'isBuilding' => false,
        'isComplete' => true,
      ),
      self::STATUS_ABORTED => array(
        'name' => pht('Aborted'),
        'icon' => 'fa-minus-circle',
        'color' => 'red',
        'color.ansi' => 'red',
        'isBuilding' => false,
        'isComplete' => true,
      ),
      self::STATUS_ERROR => array(
        'name' => pht('Unexpected Error'),
        'icon' => 'fa-minus-circle',
        'color' => 'red',
        'color.ansi' => 'red',
        'isBuilding' => false,
        'isComplete' => true,
      ),
      self::STATUS_PAUSED => array(
        'name' => pht('Paused'),
        'icon' => 'fa-pause',
        'color' => 'yellow',
        'color.ansi' => 'yellow',
        'isBuilding' => false,
        'isComplete' => false,
      ),
      self::STATUS_DEADLOCKED => array(
        'name' => pht('Deadlocked'),
        'icon' => 'fa-exclamation-circle',
        'color' => 'red',
        'color.ansi' => 'red',
        'isBuilding' => false,
        'isComplete' => true,
      ),
      self::PENDING_PAUSING => array(
        'name' => pht('Pausing'),
        'icon' => 'fa-exclamation-triangle',
        'color' => 'red',
        'color.ansi' => 'red',
        'isBuilding' => false,
        'isComplete' => false,
      ),
      self::PENDING_RESUMING => array(
        'name' => pht('Resuming'),
        'icon' => 'fa-exclamation-triangle',
        'color' => 'red',
        'color.ansi' => 'red',
        'isBuilding' => false,
        'isComplete' => false,
      ),
      self::PENDING_RESTARTING => array(
        'name' => pht('Restarting'),
        'icon' => 'fa-exclamation-triangle',
        'color' => 'red',
        'color.ansi' => 'red',
        'isBuilding' => false,
        'isComplete' => false,
      ),
      self::PENDING_ABORTING => array(
        'name' => pht('Aborting'),
        'icon' => 'fa-exclamation-triangle',
        'color' => 'red',
        'color.ansi' => 'red',
        'isBuilding' => false,
        'isComplete' => false,
      ),
    );
  }

  private static function getBuildStatusSpec($status) {
    $map = self::getBuildStatusSpecMap();
    if (isset($map[$status])) {
      return $map[$status];
    }

    return array(
      'name' => pht('Unknown ("%s")', $status),
      'icon' => 'fa-question-circle',
      'color' => 'bluegrey',
      'color.ansi' => 'magenta',
      'isBuilding' => false,
      'isComplete' => false,
    );
  }

}
