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

abstract class PhabricatorRepositoryCommitMessageDetailParser {

  private $commit;
  private $commitData;

  final public function __construct(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {
    $this->commit = $commit;
    $this->commitData = $data;
  }

  final public function getCommit() {
    return $this->commit;
  }

  final public function getCommitData() {
    return $this->commitData;
  }

  public function resolveUserPHID($user_name) {
    if (!strlen($user_name)) {
      return null;
    }

    $by_username = id(new PhabricatorUser())->loadOneWhere(
      'userName = %s',
      $user_name);
    if ($by_username) {
      return $by_username->getPHID();
    }

    // Note, real names are not guaranteed unique, which is why we do it this
    // way.
    $by_realname = id(new PhabricatorUser())->loadAllWhere(
      'realName = %s LIMIT 1',
      $user_name);
    if ($by_realname) {
      $by_realname = reset($by_realname);
      return $by_realname->getPHID();
    }

    return null;
  }

  abstract public function parseCommitDetails();

}
