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

/**
 * TODO: This might need to be concrete-extensible, but straighten out the
 * class hierarchy here.
 */
class DiffusionRequest {

  protected $callsign;
  protected $path;
  protected $line;
  protected $commit;
  protected $branch;

  protected $repository;
  protected $repositoryCommit;
  protected $repositoryCommitData;
  protected $stableCommitName;

  final private function __construct() {
    // <private>
  }

  final public static function newFromAphrontRequestDictionary(array $data) {

    $vcs = null;
    $repository = null;
    $callsign = idx($data, 'callsign');
    if ($callsign) {
      $repository = id(new PhabricatorRepository())->loadOneWhere(
        'callsign = %s',
        $callsign);
      if (!$repository) {
        throw new Exception("No such repository '{$callsign}'.");
      }
      $vcs = $repository->getVersionControlSystem();
    }

    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $class = 'DiffusionGitRequest';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $class = 'DiffusionSvnRequest';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $class = 'DiffusionMercurialRequest';
        break;
      default:
        $class = 'DiffusionRequest';
        break;
    }

    $object = new $class();

    $object->callsign   = $callsign;
    $object->repository = $repository;
    $object->line       = idx($data, 'line');
    $object->commit     = idx($data, 'commit');
    $object->path       = idx($data, 'path');

    $object->initializeFromAphrontRequestDictionary($data);

    return $object;
  }

  protected function initializeFromAphrontRequestDictionary(array $data) {

  }

  protected function parsePath($path) {
    $this->path = $path;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function getCallsign() {
    return $this->callsign;
  }

  public function getPath() {
    return $this->path;
  }

  public function getUriPath() {
    return '/diffusion/'.$this->getCallsign().'/browse/'.$this->path;
  }

  public function getLine() {
    return $this->line;
  }

  public function getCommit() {
    return $this->commit;
  }

  public function getBranch() {
    return $this->branch;
  }

  public function loadCommit() {
    if (empty($this->repositoryCommit)) {
      $repository = $this->getRepository();

      $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
        'repositoryID = %d AND commitIdentifier = %s',
        $repository->getID(),
        $this->getCommit());
      $this->repositoryCommit = $commit;
    }
    return $this->repositoryCommit;
  }

  public function loadCommitData() {
    if (empty($this->repositoryCommitData)) {
      $commit = $this->loadCommit();
      $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
        'commitID = %d',
        $commit->getID());
      if (!$data) {
        $data = new PhabricatorRepositoryCommitData();
        $data->setCommitMessage('(This commit has not fully parsed yet.)');
      }
      $this->repositoryCommitData = $data;
    }
    return $this->repositoryCommitData;
  }

  /**
   * Retrieve a stable, permanent commit name. This returns a non-symbolic
   * identifier for the current commit: e.g., a specific commit hash in git
   * (NOT a symbolic name like "origin/master") or a specific revision number
   * in SVN (NOT a symbolic name like "HEAD").
   *
   * @return string Stable commit name, like a git hash or SVN revision. Not
   *                a symbolic commit reference.
   */
  public function getStableCommitName() {
    return $this->stableCommitName;
  }

  final public function getRawCommit() {
    return $this->commit;
  }

  public function setCommit($commit) {
    $this->commit = $commit;
    return $this;
  }

  public function getCommitURIComponent($commit) {
    return $commit;
  }

  public function getBranchURIComponent($branch) {
    return $branch;
  }

}
