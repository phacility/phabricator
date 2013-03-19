<?php

/**
 * @group phriction
 */
final class PhrictionActionConstants extends PhrictionConstants {

  const ACTION_CREATE   = 'create';
  const ACTION_EDIT     = 'edit';
  const ACTION_DELETE   = 'delete';
  const ACTION_MOVE_AWAY = 'move to';
  const ACTION_MOVE_HERE = 'move here';

  public static function getActionPastTenseVerb($action) {
    static $map = array(
      self::ACTION_CREATE   => 'created',
      self::ACTION_EDIT     => 'edited',
      self::ACTION_DELETE   => 'deleted',
      self::ACTION_MOVE_AWAY => 'moved',
      self::ACTION_MOVE_HERE => 'moved',
    );

    return idx($map, $action, "brazenly {$action}'d");
  }

}
