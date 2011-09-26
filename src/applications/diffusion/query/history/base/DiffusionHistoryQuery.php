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
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $class = 'DiffusionMercurialHistoryQuery';
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

  final protected function loadHistoryForCommitIdentifiers(array $identifiers) {
    if (!$identifiers) {
      return array();
    }

    $commits = array();
    $commit_data = array();
    $path_changes = array();

    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();

    $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
      'repositoryID = %d AND commitIdentifier IN (%Ls)',
        $repository->getID(),
      $identifiers);
    $commits = mpull($commits, null, 'getCommitIdentifier');

    if (!$commits) {
      return array();
    }

    $commit_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
      'commitID in (%Ld)',
      mpull($commits, 'getID'));
    $commit_data = mpull($commit_data, null, 'getCommitID');

    $conn_r = $repository->establishConnection('r');

    $path_normal = DiffusionPathIDQuery::normalizePath($path);
    $paths = queryfx_all(
      $conn_r,
      'SELECT id, path FROM %T WHERE path IN (%Ls)',
      PhabricatorRepository::TABLE_PATH,
      array($path_normal));
    $paths = ipull($paths, 'id', 'path');
    $path_id = idx($paths, $path_normal);

    $path_changes = queryfx_all(
      $conn_r,
      'SELECT * FROM %T WHERE commitID IN (%Ld) AND pathID = %d',
      PhabricatorRepository::TABLE_PATHCHANGE,
      mpull($commits, 'getID'),
      $path_id);
    $path_changes = ipull($path_changes, null, 'commitID');

    $history = array();
    foreach ($identifiers as $identifier) {
      $item = new DiffusionPathChange();
      $item->setCommitIdentifier($identifier);
      $commit = idx($commits, $identifier);
      if ($commit) {
        $item->setCommit($commit);
        $data = idx($commit_data, $commit->getID());
        if ($data) {
          $item->setCommitData($data);
        }
        $change = idx($path_changes, $commit->getID());
        if ($change) {
          $item->setChangeType($change['changeType']);
          $item->setFileType($change['fileType']);
        }
      }
      $history[] = $item;
    }

    return $history;
  }

}
