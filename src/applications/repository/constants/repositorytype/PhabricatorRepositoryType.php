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

final class PhabricatorRepositoryType {

  const REPOSITORY_TYPE_GIT         = 'git';
  const REPOSITORY_TYPE_SVN         = 'svn';
  const REPOSITORY_TYPE_MERCURIAL   = 'hg';

  public static function getAllRepositoryTypes() {
    static $map = array(
      self::REPOSITORY_TYPE_GIT       => 'Git',
      self::REPOSITORY_TYPE_SVN       => 'Subversion',

      // TODO: Stabilize and remove caveat.
      self::REPOSITORY_TYPE_MERCURIAL => 'Mercurial (LIMITED SUPPORT!)',
    );
    return $map;
  }

  public static function getNameForRepositoryType($type) {
    $map = self::getAllRepositoryTypes();
    return idx($map, $type, 'Unknown');
  }

}
