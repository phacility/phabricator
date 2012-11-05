<?php

final class DrydockLeaseStatus extends DrydockConstants {

  const STATUS_PENDING      = 0;
  const STATUS_ACTIVE       = 1;
  const STATUS_RELEASED     = 2;
  const STATUS_BROKEN       = 3;
  const STATUS_EXPIRED      = 4;

  public static function getNameForStatus($status) {
    static $map = array(
      self::STATUS_PENDING  => 'Pending',
      self::STATUS_ACTIVE   => 'Active',
      self::STATUS_RELEASED => 'Released',
      self::STATUS_BROKEN   => 'Broken',
      self::STATUS_EXPIRED  => 'Expired',
    );

    return idx($map, $status, 'Unknown');
  }

}
