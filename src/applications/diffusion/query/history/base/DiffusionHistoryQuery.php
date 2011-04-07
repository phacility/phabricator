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

abstract class DiffusionHistoryQuery {

  private $request;
  private $limit = 100;
  private $offset = 0;

  protected $needDirectChanges;
  protected $needChildChanges;

  final private function __construct() {
    // <private>
  }

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {

    $repository = $request->getRepository();

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $class = 'DiffusionGitHistoryQuery';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $class = 'DiffusionSvnHistoryQuery';
        break;
      default:
        throw new Exception("Unsupported VCS!");
    }

    PhutilSymbolLoader::loadClass($class);
    $query = new $class();

    $query->request = $request;

    return $query;
  }

  final public function needDirectChanges($direct) {
    $this->needDirectChanges = $direct;
    return $this;
  }

  final public function needChildChanges($child) {
    $this->needChildChanges = $child;
    return $this;
  }

  final protected function getRequest() {
    return $this->request;
  }

  final public function loadHistory() {
    return $this->executeQuery();
  }

  final public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  final public function getLimit() {
    return $this->limit;
  }

  final public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  final public function getOffset() {
    return $this->offset;
  }

  abstract protected function executeQuery();
}
