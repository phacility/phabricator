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

final class DiffusionGitMergedCommitsQuery extends DiffusionMergedCommitsQuery {

  protected function executeQuery() {
    $request = $this->getRequest();
    $repository = $request->getRepository();

    list($parents) = $repository->execxLocalCommand(
      'log -n 1 --format=%s %s',
      '%P',
      $request->getCommit());
    $parents = preg_split('/\s+/', trim($parents));
    if (count($parents) < 2) {
      // This is not a merge commit, so it doesn't merge anything.
      return array();
    }

    // Get all of the commits which are not reachable from the first parent.
    // These are the commits this change merges.

    $first_parent = head($parents);
    list($logs) = $repository->execxLocalCommand(
      'log -n %d --format=%s %s %s --',
      // NOTE: "+ 1" accounts for the merge commit itself.
      $this->getLimit() + 1,
      '%H',
      $request->getCommit(),
      '^'.$first_parent);

    $hashes = explode("\n", trim($logs));

    // Remove the merge commit.
    $hashes = array_diff($hashes, array($request->getCommit()));

    return $this->loadHistoryForCommitIdentifiers($hashes);
  }

}
