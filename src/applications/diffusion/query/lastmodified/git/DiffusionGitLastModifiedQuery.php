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

final class DiffusionGitLastModifiedQuery extends DiffusionLastModifiedQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    list($hash) = $repository->execxLocalCommand(
      'log -n1 --format=%%H %s -- %s',
      $drequest->getCommit(),
      $drequest->getPath());
    $hash = trim($hash);

    $commit_data = null;

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %d AND commitIdentifier = %s',
      $repository->getID(),
      $hash);
    if ($commit) {
      $commit_data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
        'commitID = %d',
        $commit->getID());
    }

    return array($commit, $commit_data);
  }

}
