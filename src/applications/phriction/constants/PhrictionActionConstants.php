<?php

final class PhrictionActionConstants extends PhrictionConstants {

  const ACTION_CREATE   = 'create';
  const ACTION_EDIT     = 'edit';
  const ACTION_DELETE   = 'delete';
  const ACTION_MOVE_AWAY = 'move to';
  const ACTION_MOVE_HERE = 'move here';

  public static function getActionPastTenseVerb($action) {
    $map = array(
      self::ACTION_CREATE   => pht('created'),
      self::ACTION_EDIT     => pht('edited'),
      self::ACTION_DELETE   => pht('deleted'),
      self::ACTION_MOVE_AWAY => pht('moved'),
      self::ACTION_MOVE_HERE => pht('moved'),
    );

    return idx($map, $action, pht("brazenly %s'd", $action));
  }

}
