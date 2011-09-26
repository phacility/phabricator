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

final class DiffusionMercurialHistoryQuery extends DiffusionHistoryQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit_hash = $drequest->getCommit();

    $path = DiffusionPathIDQuery::normalizePath($path);

    list($stdout) = $repository->execxLocalCommand(
      'log --template %s --limit %d --branch %s --rev %s:0 -- %s',
      '{node}\\n',
      ($this->getOffset() + $this->getLimit()), // No '--skip' in Mercurial.
      $drequest->getBranch(),
      $commit_hash,
      nonempty(ltrim($path, '/'), '.'));

    $hashes = explode("\n", $stdout);
    $hashes = array_filter($hashes);
    $hashes = array_slice($hashes, $this->getOffset());

    return $this->loadHistoryForCommitIdentifiers($hashes);
  }

}
