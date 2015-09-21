<?php

final class DrydockLeaseStatus extends DrydockConstants {

  const STATUS_PENDING      = 0;
  const STATUS_ACQUIRED     = 5;
  const STATUS_ACTIVE       = 1;
  const STATUS_RELEASED     = 2;
  const STATUS_BROKEN       = 3;
  const STATUS_EXPIRED      = 4;

  public static function getNameForStatus($status) {
    $map = array(
      self::STATUS_PENDING    => pht('Pending'),
      self::STATUS_ACQUIRED   => pht('Acquired'),
      self::STATUS_ACTIVE     => pht('Active'),
      self::STATUS_RELEASED   => pht('Released'),
      self::STATUS_BROKEN     => pht('Broken'),
      self::STATUS_EXPIRED    => pht('Expired'),
    );

    return idx($map, $status, pht('Unknown'));
  }

  public static function getAllStatuses() {
    return array(
      self::STATUS_PENDING,
      self::STATUS_ACQUIRED,
      self::STATUS_ACTIVE,
      self::STATUS_RELEASED,
      self::STATUS_BROKEN,
      self::STATUS_EXPIRED,
    );
  }

}
