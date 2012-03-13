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

final class HeraldContentTypeConfig {

  const CONTENT_TYPE_DIFFERENTIAL = 'differential';
  const CONTENT_TYPE_COMMIT       = 'commit';
  const CONTENT_TYPE_MERGE        = 'merge';
  const CONTENT_TYPE_OWNERS       = 'owners';

  public static function getContentTypeMap() {
    static $map = array(
      self::CONTENT_TYPE_DIFFERENTIAL   => 'Differential Revisions',
      self::CONTENT_TYPE_COMMIT         => 'Commits',
/* TODO: Deal with this
      self::CONTENT_TYPE_MERGE          => 'Merge Requests',
      self::CONTENT_TYPE_OWNERS         => 'Owners Changes',
*/
    );
    return $map;
  }
}
