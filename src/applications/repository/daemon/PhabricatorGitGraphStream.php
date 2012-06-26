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

final class PhabricatorGitGraphStream {

  private $repository;
  private $iterator;

  private $parents        = array();
  private $dates          = array();

  public function __construct(
    PhabricatorRepository $repository,
    $start_commit) {

    $this->repository = $repository;

    $future = $repository->getLocalCommandFuture(
      "log --format=%s %s --",
      '%H%x01%P%x01%ct',
      $start_commit);

    $this->iterator = new LinesOfALargeExecFuture($future);
    $this->iterator->setDelimiter("\n");
    $this->iterator->rewind();
  }

  public function getParents($commit) {
    if (!isset($this->parents[$commit])) {
      $this->parseUntil($commit);
    }
    return $this->parents[$commit];
  }

  public function getCommitDate($commit) {
    if (!isset($this->dates[$commit])) {
      $this->parseUntil($commit);
    }
    return $this->dates[$commit];
  }

  private function parseUntil($commit) {
    if ($this->isParsed($commit)) {
      return;
    }

    $gitlog = $this->iterator;

    while ($gitlog->valid()) {
      $line = $gitlog->current();
      $gitlog->next();

      $line = trim($line);
      if (!strlen($line)) {
        break;
      }
      list($hash, $parents, $epoch) = explode("\1", $line);

      if ($parents) {
        $parents = explode(' ', $parents);
      } else {
        // First commit.
        $parents = array();
      }

      $this->dates[$hash] = $epoch;
      $this->parents[$hash] = $parents;

      if ($this->isParsed($commit)) {
        return;
      }
    }

    throw new Exception("No such commit '{$commit}' in repository!");
  }

  private function isParsed($commit) {
    return isset($this->dates[$commit]);
  }

}
