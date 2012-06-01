<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class DrydockResourceStatus extends DrydockConstants {

  const STATUS_PENDING      = 0;
  const STATUS_ALLOCATING   = 1;
  const STATUS_OPEN         = 2;
  const STATUS_CLOSED       = 3;
  const STATUS_BROKEN       = 4;
  const STATUS_DESTROYED    = 5;

  public static function getNameForStatus($status) {
    static $map = array(
      self::STATUS_PENDING      => 'Pending',
      self::STATUS_ALLOCATING   => 'Pending',
      self::STATUS_OPEN         => 'Open',
      self::STATUS_CLOSED       => 'Closed',
      self::STATUS_BROKEN       => 'Broken',
      self::STATUS_DESTROYED    => 'Destroyed',
    );

    return idx($map, $status, 'Unknown');
  }

}
