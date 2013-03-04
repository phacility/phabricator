<?php

/**
 * @group phriction
 */
final class PhrictionChangeType extends PhrictionConstants {

  const CHANGE_EDIT       = 0;
  const CHANGE_DELETE     = 1;
  const CHANGE_MOVE_HERE  = 2;
  const CHANGE_MOVE_AWAY  = 3;
  const CHANGE_STUB       = 4;

  public static function getChangeTypeLabel($const) {
    static $map = array(
      self::CHANGE_EDIT       => 'Edit',
      self::CHANGE_DELETE     => 'Delete',
      self::CHANGE_MOVE_HERE  => 'Move Here',
      self::CHANGE_MOVE_AWAY  => 'Move Away',
      self::CHANGE_STUB       => 'Created through child',
    );

    return idx($map, $const, '???');
  }

}
