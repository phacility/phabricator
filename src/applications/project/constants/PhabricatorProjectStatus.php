<?php

final class PhabricatorProjectStatus {

  const STATUS_ACTIVE       = 0;
  const STATUS_ARCHIVED     = 100;

  public static function getNameForStatus($status) {
    static $map = array(
      self::STATUS_ACTIVE     => 'Active',
      self::STATUS_ARCHIVED   => 'Archived',
    );

    return idx($map, coalesce($status, '?'), 'Unknown');
  }

  public static function getStatusMap() {
    return array(
      self::STATUS_ACTIVE   => 'Active',
      self::STATUS_ARCHIVED => 'Archived',
    );
  }

}
