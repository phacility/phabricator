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

    list($stdout) = execx(
      '(cd %s && git log '.
        '--skip=%d '.
        '-n %d '.
        '--abbrev=40 '.
        '--pretty=format:%%H '.
        '%s -- %s)',
      $local_path,
      $this->getOffset(),
      $this->getLimit(),
      $commit_hash,
      $path);

    $hashes = explode("\n", $stdout);
    $hashes = array_filter($hashes);

    $commits = array();
    $commit_data = array();
    $path_changes = array();

    $conn_r = $repository->establishConnection('r');

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

      if ($commits) {
        $path_normal = '/'.trim($path, '/');
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
