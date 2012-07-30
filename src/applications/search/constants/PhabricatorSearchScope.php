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

/**
 * @group search
 */
final class PhabricatorSearchScope {

  const SCOPE_ALL               = 'all';
  const SCOPE_OPEN_REVISIONS    = 'open-revisions';
  const SCOPE_OPEN_TASKS        = 'open-tasks';
  const SCOPE_COMMITS           = 'commits';
  const SCOPE_WIKI              = 'wiki';

  public static function getScopeOptions() {
    return array(
      self::SCOPE_ALL               => 'All Documents',
      self::SCOPE_OPEN_TASKS        => 'Open Tasks',
      self::SCOPE_WIKI              => 'Wiki Documents',
      self::SCOPE_OPEN_REVISIONS    => 'Open Revisions',
      self::SCOPE_COMMITS           => 'Commits',
    );
  }

  public static function getScopePlaceholder($scope) {
    switch ($scope) {
      case self::SCOPE_OPEN_TASKS:
        return pht('Search Open Tasks');
      case self::SCOPE_WIKI:
        return pht('Search Wiki Documents');
      case self::SCOPE_OPEN_REVISIONS:
        return pht('Search Open Revisions');
      case self::SCOPE_COMMITS:
        return pht('Search Commits');
      default:
        return pht('Search');
    }
  }

}
