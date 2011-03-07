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

abstract class PhabricatorRepositoryCommitDiscoveryDaemon
  extends PhabricatorRepositoryDaemon {

  private $repository;

  final protected function getRepository() {
    return $this->repository;
  }

  final public function run() {
    $this->repository = $this->loadRepository();

    $sleep = 15;
    while (true) {
      $found = $this->discoverCommits();
      if ($found) {
        $sleep = 15;
      } else {
        $sleep = min($sleep + 15, 60 * 15);
      }
      $this->sleep($sleep);
    }
  }

  abstract protected function discoverCommits();

}
