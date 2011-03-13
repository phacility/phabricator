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

final class DiffusionGitHistoryQuery extends DiffusionHistoryQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit_hash = $drequest->getCommit();

    $local_path = $repository->getDetail('local-path');
    $git = $drequest->getPathToGitBinary();

    list($stdout) = execx(
      '(cd %s && %s log '.
        '--skip=%d '.
        '-n %d '.
        '-t '.
        '--abbrev=40 '.
        '--pretty=format:%%H '.
        '%s -- %s)',
      $local_path,
      $git,
      $this->getOffset(),
      $this->getLimit(),
      $commit_hash,
      $path);

    $hashes = explode("\n", $stdout);
    $hashes = array_filter($hashes);

    $commits = array();
    $commit_data = array();

    if ($hashes) {
      $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
        'repositoryID = %d AND commitIdentifier IN (%Ls)',
          $repository->getID(),
        $hashes);
      $commits = mpull($commits, null, 'getCommitIdentifier');
      if ($commits) {
        $commit_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
          'commitID in (%Ld)',
          mpull($commits, 'getID'));
        $commit_data = mpull($commit_data, null, 'getCommitID');
      }
    }

    $history = array();
    foreach ($hashes as $hash) {
      $item = new DiffusionPathChange();
      $item->setCommitIdentifier($hash);
      $commit = idx($commits, $hash);
      if ($commit) {
        $item->setCommit($commit);
        $data = idx($commit_data, $commit->getID());
        if ($data) {
          $item->setCommitData($data);
        }
      }
      $history[] = $item;
    }

    return $history;
  }

}
