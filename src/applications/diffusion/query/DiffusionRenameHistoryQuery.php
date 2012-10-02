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

final class DiffusionRenameHistoryQuery {

  private $oldCommit;
  private $wasCreated;
  private $request;

  public function getWasCreated() {
    return $this->wasCreated;
  }

  public function setRequest(DiffusionRequest $request) {
    $this->request = $request;
    return $this;
  }

  public function setOldCommit($old_commit) {
    $this->oldCommit = $old_commit;
    return $this;
  }

  public function getOldCommit() {
    return $this->oldCommit;
  }

  final public function loadOldFilename() {
    $drequest = $this->request;
    $repository_id = $drequest->getRepository()->getID();
    $conn_r = id(new PhabricatorRepository())->establishConnection('r');

    $commit_id = $this->loadCommitId($this->oldCommit);
    $old_commit_sequence = $this->loadCommitSequence($commit_id);

    $path = '/'.$drequest->getPath();
    $commit_id = $this->loadCommitId($drequest->getCommit());

    do {
      $commit_sequence = $this->loadCommitSequence($commit_id);
      $change = queryfx_one(
        $conn_r,
        'SELECT pc.changeType, pc.targetCommitID, tp.path
         FROM %T p
         JOIN %T pc ON p.id = pc.pathID
         LEFT JOIN %T tp ON pc.targetPathID = tp.id
         WHERE p.pathHash = %s
         AND pc.repositoryID = %d
         AND pc.changeType IN (%d, %d)
         AND pc.commitSequence BETWEEN %d AND %d
         ORDER BY pc.commitSequence DESC
         LIMIT 1',
        PhabricatorRepository::TABLE_PATH,
        PhabricatorRepository::TABLE_PATHCHANGE,
        PhabricatorRepository::TABLE_PATH,
        md5($path),
        $repository_id,
        ArcanistDiffChangeType::TYPE_MOVE_HERE,
        ArcanistDiffChangeType::TYPE_ADD,
        $old_commit_sequence,
        $commit_sequence);
      if ($change) {
        if ($change['changeType'] == ArcanistDiffChangeType::TYPE_ADD) {
          $this->wasCreated = true;
          return $path;
        }
        $commit_id = $change['targetCommitID'];
        $path = $change['path'];
      }
    } while ($change && $path);

    return $path;
  }

  private function loadCommitId($commit_identifier) {
    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %d AND commitIdentifier = %s',
      $this->request->getRepository()->getID(),
      $commit_identifier);
    return $commit->getID();
  }

  private function loadCommitSequence($commit_id) {
    $conn_r = id(new PhabricatorRepository())->establishConnection('r');
    $path_change = queryfx_one(
      $conn_r,
      'SELECT commitSequence
       FROM %T
       WHERE repositoryID = %d AND commitID = %d
       LIMIT 1',
      PhabricatorRepository::TABLE_PATHCHANGE,
      $this->request->getRepository()->getID(),
      $commit_id);
    return reset($path_change);
  }

}
