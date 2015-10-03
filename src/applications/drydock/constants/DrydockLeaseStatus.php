<?php

final class DrydockLeaseStatus extends DrydockConstants {

  const STATUS_PENDING = 'pending';
  const STATUS_ACQUIRED = 'acquired';
  const STATUS_ACTIVE = 'active';
  const STATUS_RELEASED = 'released';
  const STATUS_BROKEN = 'broken';
  const STATUS_DESTROYED = 'destroyed';

  public static function getStatusMap() {
    return array(
      self::STATUS_PENDING => pht('Pending'),
      self::STATUS_ACQUIRED => pht('Acquired'),
      self::STATUS_ACTIVE => pht('Active'),
      self::STATUS_RELEASED => pht('Released'),
      self::STATUS_BROKEN => pht('Broken'),
      self::STATUS_DESTROYED => pht('Destroyed'),
    );
  }

  public static function getNameForStatus($status) {
    $map = self::getStatusMap();
    return idx($map, $status, pht('Unknown'));
  }

  public static function getAllStatuses() {
    return array_keys(self::getStatusMap());
  }

}
