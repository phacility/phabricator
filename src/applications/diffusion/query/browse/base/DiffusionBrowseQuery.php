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

abstract class DiffusionBrowseQuery {

  private $repository;
  private $path;
  private $commit;

  protected $reason;

  const REASON_IS_FILE          = 'is-file';
  const REASON_IS_NONEXISTENT   = 'nonexistent';

  final private function __construct() {
    // <private>
  }

  final public static function newFromRepository(
    PhabricatorRepository $repository,
    $path = '/',
    $commit = null) {

    switch ($repository->getVersionControlSystem()) {
      case 'git':
        // TODO: Verify local-path?
        $class = 'DiffusionGitBrowseQuery';
        break;
      default:
        throw new Exception("Unsupported VCS!");
    }

    PhutilSymbolLoader::loadClass($class);
    $query = new $class();

    $query->repository = $repository;
    $query->path = $path;
    $query->commit = $commit;

    return $query;
  }

  final protected function getRepository() {
    return $this->repository;
  }

  final protected function getPath() {
    return $this->path;
  }

  final protected function getCommit() {
    return $this->commit;
  }

  final public function getReasonForEmptyResultSet() {
    return $this->reason;
  }

  final public function loadPaths() {
    return $this->executeQuery();
  }

  abstract protected function executeQuery();
}
