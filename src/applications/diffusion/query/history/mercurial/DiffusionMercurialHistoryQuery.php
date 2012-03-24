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

final class DiffusionMercurialHistoryQuery extends DiffusionHistoryQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit_hash = $drequest->getStableCommitName();

    $path = DiffusionPathIDQuery::normalizePath($path);

    // NOTE: Using '' as a default path produces the correct behavior if HEAD
    // is a merge commit; using '.' does not (the merge commit is not included
    // in the log).
    $default_path = '';

    list($stdout) = $repository->execxLocalCommand(
      'log --debug --template %s --limit %d --branch %s --rev %s:0 -- %s',
      '{node};{parents}\\n',
      ($this->getOffset() + $this->getLimit()), // No '--skip' in Mercurial.
      $drequest->getBranch(),
      $commit_hash,
      nonempty(ltrim($path, '/'), $default_path));

    $lines = explode("\n", trim($stdout));
    $lines = array_slice($lines, $this->getOffset());

    $hash_list = array();
    $parent_map = array();

    $last = null;
    foreach (array_reverse($lines) as $line) {
      list($hash, $parents) = explode(';', $line);
      $parents = trim($parents);
      if (!$parents) {
        if ($last === null) {
          $parent_map[$hash] = array('...');
        } else {
          $parent_map[$hash] = array($last);
        }
      } else {
        $parents = preg_split('/\s+/', $parents);
        foreach ($parents as $parent) {
          list($plocal, $phash) = explode(':', $parent);
          if (!preg_match('/^0+$/', $phash)) {
            $parent_map[$hash][] = $phash;
          }
        }
        // This may happen for the zeroth commit in repository, both hashes
        // are "000000000...".
        if (empty($parent_map[$hash])) {
          $parent_map[$hash] = array('...');
        }
      }

      // The rendering code expects the first commit to be "mainline", like
      // Git. Flip the order so it does the right thing.
      $parent_map[$hash] = array_reverse($parent_map[$hash]);

      $hash_list[] = $hash;
      $last = $hash;
    }

    $hash_list = array_reverse($hash_list);
    $this->parents = $parent_map;

    return $this->loadHistoryForCommitIdentifiers($hash_list);
  }

}
