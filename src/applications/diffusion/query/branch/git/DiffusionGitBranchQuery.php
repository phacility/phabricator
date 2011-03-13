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

final class DiffusionGitBranchQuery extends DiffusionBranchQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    $local_path = $repository->getDetail('local-path');

    list($stdout) = execx(
      '(cd %s && git branch --verbose --no-abbrev)',
      $local_path);

    $branches = array();

    $lines = array_filter(explode("\n", $stdout));
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match('/^[ *] (\S+)\s+([a-z0-9]{40}) /', $line, $matches)) {
        throw new Exception("Failed to parse {$line}!");
      }
      $branch = new DiffusionBranchInformation();
      $branch->setName($matches[1]);
      $branch->setHeadCommitIdentifier($matches[2]);

      $branches[] = $branch;
    }

    return $branches;
  }

}
