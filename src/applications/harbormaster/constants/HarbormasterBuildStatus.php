<?php

final class HarbormasterBuildStatus extends Phobject {

  /**
   * Not currently being built.
   */
  const STATUS_INACTIVE = 'inactive';

  /**
   * Pending pick up by the Harbormaster daemon.
   */
  const STATUS_PENDING = 'pending';

  /**
   * Current building the buildable.
   */
  const STATUS_BUILDING = 'building';

  /**
   * The build has passed.
   */
  const STATUS_PASSED = 'passed';

  /**
   * The build has failed.
   */
  const STATUS_FAILED = 'failed';

  /**
   * The build has aborted.
   */
  const STATUS_ABORTED = 'aborted';

  /**
   * The build encountered an unexpected error.
   */
  const STATUS_ERROR = 'error';

  /**
   * The build has been paused.
   */
  const STATUS_PAUSED = 'paused';

  /**
   * The build has been deadlocked.
   */
  const STATUS_DEADLOCKED = 'deadlocked';


  /**
   * Get a human readable name for a build status constant.
   *
   * @param  const  Build status constant.
   * @return string Human-readable name.
   */
  public static function getBuildStatusName($status) {
    $spec = self::getBuildStatusSpec($status);
    return idx($spec, 'name', pht('Unknown ("%s")', $status));
  }

  public static function getBuildStatusMap() {
    $specs = self::getBuildStatusSpecMap();
    return ipull($specs, 'name');
  }

  public static function getBuildStatusIcon($status) {
    $spec = self::getBuildStatusSpec($status);
    return idx($spec, 'icon', 'fa-question-circle');
  }

  public static function getBuildStatusColor($status) {
    $spec = self::getBuildStatusSpec($status);
    return idx($spec, 'color', 'bluegrey');
  }

  public static function getBuildStatusANSIColor($status) {
    $spec = self::getBuildStatusSpec($status);
    return idx($spec, 'color.ansi', 'magenta');
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
      ),
      self::STATUS_PENDING => array(
        'name' => pht('Pending'),
        'icon' => 'fa-circle-o',
        'color' => 'blue',
        'color.ansi' => 'yellow',
      ),
      self::STATUS_BUILDING => array(
        'name' => pht('Building'),
        'icon' => 'fa-chevron-circle-right',
        'color' => 'blue',
        'color.ansi' => 'yellow',
      ),
      self::STATUS_PASSED => array(
        'name' => pht('Passed'),
        'icon' => 'fa-check-circle',
        'color' => 'green',
        'color.ansi' => 'green',
      ),
      self::STATUS_FAILED => array(
        'name' => pht('Failed'),
        'icon' => 'fa-times-circle',
        'color' => 'red',
        'color.ansi' => 'red',
      ),
      self::STATUS_ABORTED => array(
        'name' => pht('Aborted'),
        'icon' => 'fa-minus-circle',
        'color' => 'red',
        'color.ansi' => 'red',
      ),
      self::STATUS_ERROR => array(
        'name' => pht('Unexpected Error'),
        'icon' => 'fa-minus-circle',
        'color' => 'red',
        'color.ansi' => 'red',
      ),
      self::STATUS_PAUSED => array(
        'name' => pht('Paused'),
        'icon' => 'fa-minus-circle',
        'color' => 'dark',
        'color.ansi' => 'yellow',
      ),
      self::STATUS_DEADLOCKED => array(
        'name' => pht('Deadlocked'),
        'icon' => 'fa-exclamation-circle',
        'color' => 'red',
        'color.ansi' => 'red',
      ),
    );
  }

  private static function getBuildStatusSpec($status) {
    return idx(self::getBuildStatusSpecMap(), $status, array());
  }

}
