<?php

final class PhabricatorProjectStatus extends Phobject {

  const STATUS_ACTIVE       = 0;
  const STATUS_ARCHIVED     = 100;

  public static function getNameForStatus($status) {
    $map = array(
      self::STATUS_ACTIVE     => pht('Active'),
      self::STATUS_ARCHIVED   => pht('Archived'),
    );

    return idx($map, coalesce($status, '?'), pht('Unknown'));
  }

  public static function getStatusMap() {
    return array(
      self::STATUS_ACTIVE   => pht('Active'),
      self::STATUS_ARCHIVED => pht('Archived'),
    );
  }

}
