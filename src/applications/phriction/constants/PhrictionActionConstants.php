<?php

/**
 * @group phriction
 */
final class PhrictionActionConstants extends PhrictionConstants {

  const ACTION_CREATE   = 'create';
  const ACTION_EDIT     = 'edit';
  const ACTION_DELETE   = 'delete';

  public static function getActionPastTenseVerb($action) {
    static $map = array(
      self::ACTION_CREATE   => 'created',
      self::ACTION_EDIT     => 'edited',
      self::ACTION_DELETE   => 'deleted',
    );

    return idx($map, $action, "brazenly {$action}'d");
  }

}
