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

final class DiffusionMercurialMergedCommitsQuery
  extends DiffusionMergedCommitsQuery {

  protected function executeQuery() {
    $request = $this->getRequest();
    $repository = $request->getRepository();

    list($parents) = $repository->execxLocalCommand(
      'parents --template=%s --rev %s',
      '{node}\\n',
      $request->getCommit());
    $parents = explode("\n", trim($parents));

    if (count($parents) < 2) {
      // Not a merge commit.
      return array();
    }

    // NOTE: In Git, the first parent is the "mainline". In Mercurial, the
    // second parent is the "mainline" (the way 'git merge' and 'hg merge'
    // work is also reversed).

    $last_parent = last($parents);
    list($logs) = $repository->execxLocalCommand(
      'log --template=%s --follow --limit %d --rev %s:0 --prune %s --',
      '{node}\\n',
      $this->getLimit() + 1,
      $request->getCommit(),
      $last_parent);

    $hashes = explode("\n", trim($logs));

    // Remove the merge commit.
    $hashes = array_diff($hashes, array($request->getCommit()));

    return $this->loadHistoryForCommitIdentifiers($hashes);
  }

}
