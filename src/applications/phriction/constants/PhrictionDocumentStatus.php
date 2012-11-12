<?php

/**
 * @group phriction
 */
final class PhrictionDocumentStatus extends PhrictionConstants {

  const STATUS_EXISTS     = 0;
  const STATUS_DELETED    = 1;

  public static function getConduitConstant($const) {
    static $map = array(
      self::STATUS_EXISTS   => 'exists',
      self::STATUS_DELETED   => 'deleted',
    );

    return idx($map, $const, 'unknown');
  }

}
