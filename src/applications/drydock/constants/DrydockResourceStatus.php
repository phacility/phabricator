<?php

final class DrydockResourceStatus extends DrydockConstants {

  const STATUS_PENDING      = 0;
  const STATUS_OPEN         = 1;
  const STATUS_CLOSED       = 2;
  const STATUS_BROKEN       = 3;
  const STATUS_DESTROYED    = 4;

  public static function getNameForStatus($status) {
    static $map = array(
      self::STATUS_PENDING      => 'Pending',
      self::STATUS_OPEN         => 'Open',
      self::STATUS_CLOSED       => 'Closed',
      self::STATUS_BROKEN       => 'Broken',
      self::STATUS_DESTROYED    => 'Destroyed',
    );

    return idx($map, $status, 'Unknown');
  }

}
