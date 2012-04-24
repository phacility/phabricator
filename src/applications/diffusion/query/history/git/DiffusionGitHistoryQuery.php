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

final class DiffusionGitHistoryQuery extends DiffusionHistoryQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit_hash = $drequest->getCommit();

    list($stdout) = $repository->execxLocalCommand(
      'log '.
        '--skip=%d '.
        '-n %d '.
        '--pretty=format:%s '.
        '%s -- %C',
      $this->getOffset(),
      $this->getLimit(),
      '%H:%P',
      $commit_hash,
      // Git omits merge commits if the path is provided, even if it is empty.
      (strlen($path) ? csprintf('%s', $path) : ''));

    $lines = explode("\n", trim($stdout));
    $lines = array_filter($lines);
    if (!$lines) {
      return array();
    }

    $hash_list = array();
    $parent_map = array();
    foreach ($lines as $line) {
      list($hash, $parents) = explode(":", $line);
      $hash_list[] = $hash;
      $parent_map[$hash] = preg_split('/\s+/', $parents);
    }

    $this->parents = $parent_map;

    return $this->loadHistoryForCommitIdentifiers($hash_list);
  }

}
