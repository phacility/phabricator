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

    $local_path = $repository->getDetail('local-path');

    list($stdout) = $repository->execxLocalCommand(
      'branch -r --verbose --no-abbrev');

    $branches = array();
    foreach (self::parseGitRemoteBranchOutput($stdout) as $name => $head) {
      $branch = new DiffusionBranchInformation();
      $branch->setName($name);
      $branch->setHeadCommitIdentifier($head);
      $branches[] = $branch;
    }

    return $branches;
  }

  public static function parseGitRemoteBranchOutput($stdout) {
    $map = array();

    $lines = array_filter(explode("\n", $stdout));
    foreach ($lines as $line) {
      $matches = null;
      if (preg_match('/^  (\S+)\s+-> (\S+)$/', $line, $matches)) {
        // This is a line like:
        //
        //   origin/HEAD          -> origin/master
        //
        // ...which we don't currently do anything interesting with, although
        // in theory we could use it to automatically choose the default
        // branch.
        continue;
      }
      if (!preg_match('/^[ *] (\S+)\s+([a-z0-9]{40}) /', $line, $matches)) {
        throw new Exception("Failed to parse {$line}!");
      }
      $map[$matches[1]] = $matches[2];
    }

    return $map;
  }

}
