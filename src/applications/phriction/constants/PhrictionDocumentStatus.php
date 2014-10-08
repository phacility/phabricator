<?php

final class PhrictionDocumentStatus extends PhrictionConstants {

  const STATUS_EXISTS     = 0;
  const STATUS_DELETED    = 1;
  const STATUS_MOVED      = 2;
  const STATUS_STUB       = 3;

  public static function getConduitConstant($const) {
    static $map = array(
      self::STATUS_EXISTS    => 'exists',
      self::STATUS_DELETED   => 'deleted',
      self::STATUS_MOVED     => 'moved',
      self::STATUS_STUB      => 'stubbed',
    );

    return idx($map, $const, 'unknown');
  }

}
