<?php

/**
 * @group phriction
 */
final class PhrictionChangeType extends PhrictionConstants {

  const CHANGE_EDIT       = 0;
  const CHANGE_DELETE     = 1;
  const CHANGE_MOVE_HERE  = 2;
  const CHANGE_MOVE_AWAY  = 3;

  public static function getChangeTypeLabel($const) {
    static $map = array(
      self::CHANGE_EDIT       => 'Edit',
      self::CHANGE_DELETE     => 'Delete',
      self::CHANGE_MOVE_HERE  => 'Move Here',
      self::CHANGE_MOVE_AWAY  => 'Move Away',
    );

    return idx($map, $const, '???');
  }

}
