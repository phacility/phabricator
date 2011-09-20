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
  private $commitCache = array();

  final protected function getRepository() {
    return $this->repository;
  }

  final public function run() {
    $this->repository = $this->loadRepository();

    $sleep = $this->repository->getDetail('pull-frequency');
    while (true) {
      $this->discoverCommits();
      $this->sleep(max(2, $sleep));
    }
  }

  final public function runOnce() {
    $this->repository = $this->loadRepository();
    $this->discoverCommits();
  }

  protected function isKnownCommit($target) {
    if (isset($this->commitCache[$target])) {
      return true;
    }

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %s AND commitIdentifier = %s',
      $this->getRepository()->getID(),
      $target);

    if (!$commit) {
      return false;
    }

    $this->commitCache[$target] = true;
    while (count($this->commitCache) > 64) {
      array_shift($this->commitCache);
    }

    return true;
  }

  protected function recordCommit($commit_identifier, $epoch) {
    $repository = $this->getRepository();

    $commit = new PhabricatorRepositoryCommit();
    $commit->setRepositoryID($repository->getID());
    $commit->setCommitIdentifier($commit_identifier);
    $commit->setEpoch($epoch);

    try {
      $commit->save();
      $event = new PhabricatorTimelineEvent(
        'cmit',
        array(
          'id' => $commit->getID(),
        ));
      $event->recordEvent();

      queryfx(
        $repository->establishConnection('w'),
        'INSERT INTO %T (repositoryID, size, lastCommitID, epoch)
          VALUES (%d, 1, %d, %d)
          ON DUPLICATE KEY UPDATE
            size = size + 1,
            lastCommitID =
              IF(VALUES(epoch) > epoch, VALUES(lastCommitID), lastCommitID),
            epoch = IF(VALUES(epoch) > epoch, VALUES(epoch), epoch)',
        PhabricatorRepository::TABLE_SUMMARY,
        $repository->getID(),
        $commit->getID(),
        $epoch);

      $this->commitCache[$commit_identifier] = true;
    } catch (AphrontQueryDuplicateKeyException $ex) {
      // Ignore. This can happen because we discover the same new commit
      // more than once when looking at history, or because of races or
      // data inconsistency or cosmic radiation; in any case, we're still
      // in a good state if we ignore the failure.
      $this->commitCache[$commit_identifier] = true;
    }

    $this->stillWorking();
  }

  abstract protected function discoverCommits();

}
