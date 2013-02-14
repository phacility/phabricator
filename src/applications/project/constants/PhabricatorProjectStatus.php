<?php

final class PhabricatorProjectStatus {

  const STATUS_ACTIVE       = 0;
  const STATUS_ARCHIVED     = 100;

  public static function getNameForStatus($status) {
    $map = array(
      self::STATUS_ACTIVE     => pht('Active'),
      self::STATUS_ARCHIVED   => pht('Archived'),
    );

    return idx($map, coalesce($status, '?'), 'Unknown');
  }

  public static function getIconForStatus($status) {
    $map = array(
      self::STATUS_ACTIVE     => 'check',
      self::STATUS_ARCHIVED   => 'disable',
    );

    return idx($map, $status);
  }

  public static function getStatusMap() {
    return array(
      self::STATUS_ACTIVE   => 'Active',
      self::STATUS_ARCHIVED => 'Archived',
    );
  }

}
