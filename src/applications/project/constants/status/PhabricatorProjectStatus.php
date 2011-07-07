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

final class PhabricatorProjectStatus {

  const UNKNOWN         = 0;
  const NOT_STARTED     = 1;
  const IN_PROGRESS     = 2;
  const REVIEW_PROCESS  = 3;
  const RELEASED        = 4;
  const COMPLETED       = 5;
  const DEFERRED        = 6;
  const ONGOING         = 7;


  public static function getNameForStatus($status) {
    static $map = array(
      self::UNKNOWN         => '',
      self::NOT_STARTED     => 'Not started',
      self::IN_PROGRESS     => 'In progress',
      self::ONGOING         => 'Ongoing',
      self::REVIEW_PROCESS  => 'Review process',
      self::RELEASED        => 'Released',
      self::COMPLETED       => 'Completed',
      self::DEFERRED        => 'Deferred',
    );

    return idx($map, coalesce($status, '?'), $map[self::UNKNOWN]);
  }

  public static function getStatusMap() {
    return array(
      self::UNKNOWN         => 'Who knows?',
      self::NOT_STARTED     => 'Not started',
      self::IN_PROGRESS     => 'In progress',
      self::ONGOING         => 'Ongoing',
      self::REVIEW_PROCESS  => 'Review process',
      self::RELEASED        => 'Released',
      self::COMPLETED       => 'Completed',
      self::DEFERRED        => 'Deferred',
    );
  }
}
