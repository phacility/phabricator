<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group maniphest
 */
class ManiphestAction extends PhrictionConstants {

  const ACTION_CREATE   = 'create';
  const ACTION_CLOSE    = 'close';
  const ACTION_UPDATE   = 'update';
  const ACTION_ASSIGN   = 'assign';

  public static function getActionPastTenseVerb($action) {
    static $map = array(
      self::ACTION_CREATE   => 'created',
      self::ACTION_CLOSE    => 'closed',
      self::ACTION_UPDATE   => 'updated',
      self::ACTION_ASSIGN   => 'assigned',
    );

    return idx($map, $action, "brazenly {$action}'d");
  }

  /**
   * If a group of transactions contain several actions, select the "strongest"
   * action. For instance, a close is stronger than an update, because we want
   * to render "User U closed task T" instead of "User U updated task T" when
   * a user closes a task.
   */
  public static function selectStrongestAction(array $actions) {
    static $strengths = array(
      self::ACTION_UPDATE => 0,
      self::ACTION_ASSIGN => 1,
      self::ACTION_CREATE => 2,
      self::ACTION_CLOSE  => 3,
    );

    $strongest = null;
    $strength = -1;
    foreach ($actions as $action) {
      if ($strengths[$action] > $strength) {
        $strength = $strengths[$action];
        $strongest = $action;
      }
    }
    return $strongest;
  }

}
