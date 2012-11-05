<?php

/**
 * @group maniphest
 */
final class ManiphestTaskStatus extends ManiphestConstants {

  const STATUS_OPEN               = 0;
  const STATUS_CLOSED_RESOLVED    = 1;
  const STATUS_CLOSED_WONTFIX     = 2;
  const STATUS_CLOSED_INVALID     = 3;
  const STATUS_CLOSED_DUPLICATE   = 4;
  const STATUS_CLOSED_SPITE       = 5;

  public static function getTaskStatusMap() {
    return array(
      self::STATUS_OPEN                 => 'Open',
      self::STATUS_CLOSED_RESOLVED      => 'Resolved',
      self::STATUS_CLOSED_WONTFIX       => 'Wontfix',
      self::STATUS_CLOSED_INVALID       => 'Invalid',
      self::STATUS_CLOSED_DUPLICATE     => 'Duplicate',
      self::STATUS_CLOSED_SPITE         => 'Spite',
    );
  }

  public static function getTaskStatusFullName($status) {
    $map = array(
      self::STATUS_OPEN                 => 'Open',
      self::STATUS_CLOSED_RESOLVED      => 'Closed, Resolved',
      self::STATUS_CLOSED_WONTFIX       => 'Closed, Wontfix',
      self::STATUS_CLOSED_INVALID       => 'Closed, Invalid',
      self::STATUS_CLOSED_DUPLICATE     => 'Closed, Duplicate',
      self::STATUS_CLOSED_SPITE         => 'Closed out of Spite',
    );
    return idx($map, $status, '???');
  }

}
