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

final class DiffusionSvnHistoryQuery extends DiffusionHistoryQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    $conn_r = $repository->establishConnection('r');

    $paths = queryfx_all(
      $conn_r,
      'SELECT id, path FROM %T WHERE pathHash IN (%Ls)',
      PhabricatorRepository::TABLE_PATH,
      array(md5('/'.trim($path, '/'))));
    $paths = ipull($paths, 'id', 'path');
    $path_id = $paths['/'.trim($path, '/')];

    $filter_query = '';
    if ($this->needDirectChanges) {
      if ($this->needChildChanges) {
        $type = DifferentialChangeType::TYPE_CHILD;
        $filter_query = 'AND (isDirect = 1 OR changeType = '.$type.')';
      } else {
        $filter_query = 'AND (isDirect = 1)';
      }
    }

    $history_data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T WHERE repositoryID = %d AND pathID = %d
        AND commitSequence <= %d
        %Q
        ORDER BY commitSequence DESC
        LIMIT %d, %d',
      PhabricatorRepository::TABLE_PATHCHANGE,
      $repository->getID(),
      $path_id,
      $commit ? $commit : 0x7FFFFFFF,
      $filter_query,
      $this->getOffset(),
      $this->getLimit());

    $commits = array();
    $commit_data = array();

    $commit_ids = ipull($history_data, 'commitID');
    if ($commit_ids) {
      $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
        'id IN (%Ld)',
        $commit_ids);
      if ($commits) {
        $commit_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
          'commitID in (%Ld)',
          $commit_ids);
        $commit_data = mpull($commit_data, null, 'getCommitID');
      }
    }

    $history = array();
    foreach ($history_data as $row) {
      $item = new DiffusionPathChange();

      $commit = idx($commits, $row['commitID']);
      if ($commit) {
        $item->setCommit($commit);
        $item->setCommitIdentifier($commit->getCommitIdentifier());
        $data = idx($commit_data, $commit->getID());
        if ($data) {
          $item->setCommitData($data);
        }
      }

      $item->setChangeType($row['changeType']);
      $item->setFileType($row['fileType']);


      $history[] = $item;
    }

    return $history;
  }

}
