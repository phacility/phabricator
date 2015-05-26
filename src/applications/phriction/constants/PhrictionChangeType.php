<?php

final class PhrictionChangeType extends PhrictionConstants {

  const CHANGE_EDIT       = 0;
  const CHANGE_DELETE     = 1;
  const CHANGE_MOVE_HERE  = 2;
  const CHANGE_MOVE_AWAY  = 3;
  const CHANGE_STUB       = 4;

  public static function getChangeTypeLabel($const) {
    $map = array(
      self::CHANGE_EDIT       => pht('Edit'),
      self::CHANGE_DELETE     => pht('Delete'),
      self::CHANGE_MOVE_HERE  => pht('Move Here'),
      self::CHANGE_MOVE_AWAY  => pht('Move Away'),
      self::CHANGE_STUB       => pht('Created through child'),
    );

    return idx($map, $const, pht('Unknown'));
  }

}
