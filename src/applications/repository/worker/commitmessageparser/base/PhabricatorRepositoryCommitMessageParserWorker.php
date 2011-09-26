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

abstract class PhabricatorRepositoryCommitMessageParserWorker
  extends PhabricatorRepositoryCommitParserWorker {

  abstract protected function getCommitHashes(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit);

  final protected function updateCommitData($author, $message) {
    $commit = $this->commit;

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());
    if (!$data) {
      $data = new PhabricatorRepositoryCommitData();
    }
    $data->setCommitID($commit->getID());
    $data->setAuthorName($author);
    $data->setCommitMessage($message);

    $repository = $this->repository;
    $detail_parser = $repository->getDetail(
      'detail-parser',
      'PhabricatorRepositoryDefaultCommitMessageDetailParser');

    if ($detail_parser) {
      PhutilSymbolLoader::loadClass($detail_parser);
      $parser_obj = newv($detail_parser, array($commit, $data));
      $parser_obj->parseCommitDetails();
    }

    $data->save();

    $conn_w = id(new DifferentialRevision())->establishConnection('w');

    // NOTE: The `differential_commit` table has a unique ID on `commitPHID`,
    // preventing more than one revision from being associated with a commit.
    // Generally this is good and desirable, but with the advent of hash
    // tracking we may end up in a situation where we match several different
    // revisions. We just kind of ignore this and pick one, we might want to
    // revisit this and do something differently. (If we match several revisions
    // someone probably did something very silly, though.)

    $revision_id = $data->getCommitDetail('differential.revisionID');
    if (!$revision_id) {
      $hashes = $this->getCommitHashes(
        $this->repository,
        $this->commit);
      if ($hashes) {
        $sql = array();
        foreach ($hashes as $info) {
          list($type, $hash) = $info;
          $sql[] = qsprintf(
            $conn_w,
            '(type = %s AND hash = %s)',
            $type,
            $hash);
        }
        $revision = queryfx_one(
          $conn_w,
          'SELECT revisionID FROM %T WHERE %Q LIMIT 1',
          DifferentialRevisionHash::TABLE_NAME,
          implode(' OR ', $sql));
        if ($revision) {
          $revision_id = $revision['revisionID'];
        }
      }
    }

    if ($revision_id) {
      $revision = id(new DifferentialRevision())->load($revision_id);
      if ($revision) {
        queryfx(
          $conn_w,
          'INSERT IGNORE INTO %T (revisionID, commitPHID) VALUES (%d, %s)',
          DifferentialRevision::TABLE_COMMIT,
          $revision->getID(),
          $commit->getPHID());

        if ($revision->getStatus() != DifferentialRevisionStatus::COMMITTED) {
          $editor = new DifferentialCommentEditor(
            $revision,
            $revision->getAuthorPHID(),
            DifferentialAction::ACTION_COMMIT);
          $editor->save();
        }
      }
    }
  }

}
