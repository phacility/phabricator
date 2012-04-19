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

    $author_phid = $data->getCommitDetail('authorPHID');
    if ($author_phid) {
      $commit->setAuthorPHID($author_phid);
      $commit->save();
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

        $query = new DifferentialRevisionQuery();
        $query->withCommitHashes($hashes);
        $revisions = $query->execute();

        if (!empty($revisions)) {
          $revision = $this->identifyBestRevision($revisions);
          $revision_id = $revision->getID();
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

        if ($revision->getStatus() !=
            ArcanistDifferentialRevisionStatus::COMMITTED) {

          $date_committed = $this->getDateCommitted($commit);
          if ($date_committed) {
            $revision->setDateCommitted($date_committed);
          }

          $message = null;
          $committer = $data->getCommitDetail('authorPHID');
          if (!$committer) {
            $committer = $revision->getAuthorPHID();
            $message = 'Change committed by '.$data->getAuthorName().'.';
          }
          $editor = new DifferentialCommentEditor(
            $revision,
            $committer,
            DifferentialAction::ACTION_COMMIT);
          $editor->setIsDaemonWorkflow(true);
          $editor->setMessage($message)->save();
        }
      }
    }
  }

  protected function getDateCommitted(PhabricatorRepositoryCommit $commit) {
    return null;
  }

  /**
   * When querying for revisions by hash, more than one revision may be found.
   * This function identifies the "best" revision from such a set.  Typically,
   * there is only one revision found.   Otherwise, we try to pick an accepted
   * revision first, followed by an open revision, and otherwise we go with a
   * committed or abandoned revision as a last resort.
   */
  private function identifyBestRevision(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');
    // get the simplest, common case out of the way
    if (count($revisions) == 1) {
      return reset($revisions);
    }

    $first_choice = array();
    $second_choice = array();
    $third_choice = array();
    foreach ($revisions as $revision) {
      switch ($revision->getStatus()) {
        // "Accepted" revisions -- ostensibly what we're looking for!
        case ArcanistDifferentialRevisionStatus::ACCEPTED:
          $first_choice[] = $revision;
          break;
        // "Open" revisions
        case ArcanistDifferentialRevisionStatus::NEEDS_REVIEW:
        case ArcanistDifferentialRevisionStatus::NEEDS_REVISION:
          $second_choice[] = $revision;
          break;
        // default is a wtf? here
        default:
        case ArcanistDifferentialRevisionStatus::ABANDONED:
        case ArcanistDifferentialRevisionStatus::COMMITTED:
          $third_choice[] = $revision;
          break;
      }
    }

    // go down the ladder like a bro at last call
    if (!empty($first_choice)) {
      return $this->identifyMostRecentRevision($first_choice);
    }
    if (!empty($second_choice)) {
      return $this->identifyMostRecentRevision($second_choice);
    }
    if (!empty($third_choice)) {
      return $this->identifyMostRecentRevision($third_choice);
    }
  }

  /**
   * Given a set of revisions, returns the revision with the latest
   * updated time.   This is ostensibly the most recent revision.
   */
  private function identifyMostRecentRevision(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');
    $revisions = msort($revisions, 'getDateModified');
    return end($revisions);
  }
}
