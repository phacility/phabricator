<?php

final class DrydockLeaseStatus extends DrydockConstants {

  const STATUS_PENDING = 'pending';
  const STATUS_ACQUIRED = 'acquired';
  const STATUS_ACTIVE = 'active';
  const STATUS_RELEASED = 'released';
  const STATUS_BROKEN = 'broken';
  const STATUS_DESTROYED = 'destroyed';

  public static function getNameForStatus($status) {
    $map = array(
      self::STATUS_PENDING => pht('Pending'),
      self::STATUS_ACQUIRED => pht('Acquired'),
      self::STATUS_ACTIVE => pht('Active'),
      self::STATUS_RELEASED => pht('Released'),
      self::STATUS_BROKEN => pht('Broken'),
      self::STATUS_DESTROYED => pht('Destroyed'),
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
      self::STATUS_DESTROYED,
    );
  }

}
