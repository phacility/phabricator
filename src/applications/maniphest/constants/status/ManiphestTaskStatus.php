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
final class ManiphestTaskStatus extends ManiphestConstants {

  const STATUS_OPEN               = 0;
  const STATUS_CLOSED_RESOLVED    = 1;
  const STATUS_CLOSED_WONTFIX     = 2;
  const STATUS_CLOSED_INVALID     = 3;
  const STATUS_CLOSED_DUPLICATE   = 4;
  const STATUS_CLOSED_SPITE       = 5;

  public static function getTaskStatusMap() {
    return array(
      self::STATUS_OPEN                 => 'Open',
      self::STATUS_CLOSED_RESOLVED      => 'Resolved',
      self::STATUS_CLOSED_WONTFIX       => 'Wontfix',
      self::STATUS_CLOSED_INVALID       => 'Invalid',
      self::STATUS_CLOSED_DUPLICATE     => 'Duplicate',
      self::STATUS_CLOSED_SPITE         => 'Spite',
    );
  }

  public static function getTaskStatusFullName($status) {
    $map = array(
      self::STATUS_OPEN                 => 'Open',
      self::STATUS_CLOSED_RESOLVED      => 'Closed, Resolved',
      self::STATUS_CLOSED_WONTFIX       => 'Closed, Wontfix',
      self::STATUS_CLOSED_INVALID       => 'Closed, Invalid',
      self::STATUS_CLOSED_DUPLICATE     => 'Closed, Duplicate',
      self::STATUS_CLOSED_SPITE         => 'Closed out of Spite',
    );
    return idx($map, $status, '???');
  }

}
