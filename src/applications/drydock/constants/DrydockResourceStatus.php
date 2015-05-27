<?php

final class DrydockResourceStatus extends DrydockConstants {

  const STATUS_PENDING      = 0;
  const STATUS_OPEN         = 1;
  const STATUS_CLOSED       = 2;
  const STATUS_BROKEN       = 3;
  const STATUS_DESTROYED    = 4;

  public static function getNameForStatus($status) {
    $map = array(
      self::STATUS_PENDING      => pht('Pending'),
      self::STATUS_OPEN         => pht('Open'),
      self::STATUS_CLOSED       => pht('Closed'),
      self::STATUS_BROKEN       => pht('Broken'),
      self::STATUS_DESTROYED    => pht('Destroyed'),
    );

    return idx($map, $status, pht('Unknown'));
  }

  public static function getAllStatuses() {
    return array(
     self::STATUS_PENDING,
     self::STATUS_OPEN,
     self::STATUS_CLOSED,
     self::STATUS_BROKEN,
     self::STATUS_DESTROYED,
    );
  }

}
