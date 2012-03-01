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

abstract class DiffusionFileContentQuery {

  private $request;
  private $needsBlame;
  private $fileContent;

  final private function __construct() {
    // <private>
  }

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {

    $repository = $request->getRepository();

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $class = 'DiffusionGitFileContentQuery';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $class = 'DiffusionMercurialFileContentQuery';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $class = 'DiffusionSvnFileContentQuery';
        break;
      default:
        throw new Exception("Unsupported VCS!");
    }

    PhutilSymbolLoader::loadClass($class);
    $query = new $class();

    $query->request = $request;

    return $query;
  }

  public function getSupportsBlameOnBlame() {
    return false;
  }

  public function getPrevRev($rev) {
    // TODO: support git once the 'parent' info of a commit is saved
    // to the database.
    throw new Exception("Unsupported VCS!");
  }

  final protected function getRequest() {
    return $this->request;
  }

  final public function loadFileContent() {
    $this->fileContent = $this->executeQuery();
  }

  abstract protected function executeQuery();

  final public function getRawData() {
    return $this->fileContent->getCorpus();
  }

  final public function getBlameData() {
    $raw_data = $this->getRawData();

    $text_list = array();
    $rev_list = array();
    $blame_dict = array();

    if (!$this->getNeedsBlame()) {
      $text_list = explode("\n", rtrim($raw_data));
    } else {
      foreach (explode("\n", rtrim($raw_data)) as $k => $line) {
        list($rev_id, $author, $text) = $this->tokenizeLine($line);

        $text_list[$k] = $text;
        $rev_list[$k] = $rev_id;

        if (!isset($blame_dict[$rev_id]) &&
            !isset($blame_dict[$rev_id]['author'] )) {
          $blame_dict[$rev_id]['author'] = $author;
        }
      }

      $repository = $this->getRequest()->getRepository();

      $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
        'repositoryID = %d AND commitIdentifier IN (%Ls)', $repository->getID(),
        array_unique($rev_list));

      foreach ($commits as $commit) {
        $blame_dict[$commit->getCommitIdentifier()]['epoch'] =
          $commit->getEpoch();
      }

      $commits_data = array();
      if ($commits) {
        $commits_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
          'commitID IN (%Ls)',
          mpull($commits, 'getID'));
      }

      $phids = array();
      foreach ($commits_data as $data) {
        $phids[] = $data->getCommitDetail('authorPHID');
      }

      $handles = id(new PhabricatorObjectHandleData(array_unique($phids)))
        ->loadHandles();

      foreach ($commits_data as $data) {
        if ($data->getCommitDetail('authorPHID')) {
          $commit_identifier =
            $commits[$data->getCommitID()]->getCommitIdentifier();
          $blame_dict[$commit_identifier]['handle'] =
            $handles[$data->getCommitDetail('authorPHID')];
        }
      }
   }

    return array($text_list, $rev_list, $blame_dict);
  }

  abstract protected function tokenizeLine($line);

  public function setNeedsBlame($needs_blame) {
    $this->needsBlame = $needs_blame;
  }

  public function getNeedsBlame() {
    return $this->needsBlame;
  }
}
