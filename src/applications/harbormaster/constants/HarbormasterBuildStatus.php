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
    $map = self::getBuildStatusMap();
    return idx($map, $status, pht('Unknown ("%s")', $status));
  }

  public static function getBuildStatusMap() {
    return array(
      self::STATUS_INACTIVE => pht('Inactive'),
      self::STATUS_PENDING => pht('Pending'),
      self::STATUS_BUILDING => pht('Building'),
      self::STATUS_PASSED => pht('Passed'),
      self::STATUS_FAILED => pht('Failed'),
      self::STATUS_ABORTED => pht('Aborted'),
      self::STATUS_ERROR => pht('Unexpected Error'),
      self::STATUS_PAUSED => pht('Paused'),
      self::STATUS_DEADLOCKED => pht('Deadlocked'),
    );
  }

  public static function getBuildStatusIcon($status) {
    switch ($status) {
      case self::STATUS_INACTIVE:
      case self::STATUS_PENDING:
        return PHUIStatusItemView::ICON_OPEN;
      case self::STATUS_BUILDING:
        return PHUIStatusItemView::ICON_RIGHT;
      case self::STATUS_PASSED:
        return PHUIStatusItemView::ICON_ACCEPT;
      case self::STATUS_FAILED:
        return PHUIStatusItemView::ICON_REJECT;
      case self::STATUS_ABORTED:
        return PHUIStatusItemView::ICON_MINUS;
      case self::STATUS_ERROR:
        return PHUIStatusItemView::ICON_MINUS;
      case self::STATUS_PAUSED:
        return PHUIStatusItemView::ICON_MINUS;
      case self::STATUS_DEADLOCKED:
        return PHUIStatusItemView::ICON_WARNING;
      default:
        return PHUIStatusItemView::ICON_QUESTION;
    }
  }

  public static function getBuildStatusColor($status) {
    switch ($status) {
      case self::STATUS_INACTIVE:
        return 'dark';
      case self::STATUS_PENDING:
      case self::STATUS_BUILDING:
        return 'blue';
      case self::STATUS_PASSED:
        return 'green';
      case self::STATUS_FAILED:
      case self::STATUS_ABORTED:
      case self::STATUS_ERROR:
      case self::STATUS_DEADLOCKED:
        return 'red';
      case self::STATUS_PAUSED:
        return 'dark';
      default:
        return 'bluegrey';
    }
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

}
