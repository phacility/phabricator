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

final class DiffusionMercurialLastModifiedQuery
  extends DiffusionLastModifiedQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();

    // TODO: Share some of this with History query.
    list($hash) = $repository->execxLocalCommand(
      'log --template %s --limit 1 --branch %s --rev %s:0 -- %s',
      '{node}',
      $drequest->getBranch(),
      $drequest->getCommit(),
      nonempty(ltrim($path, '/'), '.'));

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %d AND commitIdentifier = %s',
      $repository->getID(),
      $hash);

    if ($commit) {
      $commit_data = $commit->loadCommitData();
    } else {
      $commit_data = null;
    }

    return array($commit, $commit_data);
  }

}
