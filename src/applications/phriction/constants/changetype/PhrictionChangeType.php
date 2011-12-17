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
