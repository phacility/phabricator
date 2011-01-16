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

final class DifferentialRevisionStatus {

  const NEEDS_REVIEW      = 0;
  const NEEDS_REVISION    = 1;
  const ACCEPTED          = 2;
  const COMMITTED         = 3;
  const ABANDONED         = 4;

  public static function getNameForRevisionStatus($status) {
    static $map = array(
      self::NEEDS_REVIEW      => 'Needs Review',
      self::NEEDS_REVISION    => 'Needs Revision',
      self::ACCEPTED          => 'Accepted',
      self::COMMITTED         => 'Committed',
      self::ABANDONED         => 'Abandoned',
    );

    return idx($map, coalesce($status, '?'), 'Unknown');
  }

}
