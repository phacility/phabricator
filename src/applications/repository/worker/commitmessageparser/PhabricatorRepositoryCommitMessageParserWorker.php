<?php

abstract class PhabricatorRepositoryCommitMessageParserWorker
  extends PhabricatorRepositoryCommitParserWorker {

  abstract protected function parseCommitWithRef(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    DiffusionCommitRef $ref);

  final protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $viewer = PhabricatorUser::getOmnipotentUser();

    $refs_raw = DiffusionQuery::callConduitWithDiffusionRequest(
      $viewer,
      DiffusionRequest::newFromDictionary(
        array(
          'repository' => $repository,
          'user' => $viewer,
        )),
      'diffusion.querycommits',
      array(
        'phids' => array($commit->getPHID()),
        'bypassCache' => true,
        'needMessages' => true,
      ));

    if (empty($refs_raw['data'])) {
      throw new Exception(
        pht(
          'Unable to retrieve details for commit "%s"!',
          $commit->getPHID()));
    }

    $ref = DiffusionCommitRef::newFromConduitResult(head($refs_raw['data']));

    $this->parseCommitWithRef($repository, $commit, $ref);
  }

  final protected function updateCommitData(DiffusionCommitRef $ref) {
    $commit = $this->commit;
    $author = $ref->getAuthor();
    $message = $ref->getMessage();
    $committer = $ref->getCommitter();
    $hashes = $ref->getHashes();

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());
    if (!$data) {
      $data = new PhabricatorRepositoryCommitData();
    }
    $data->setCommitID($commit->getID());
    $data->setAuthorName(id(new PhutilUTF8StringTruncator())
      ->setMaximumBytes(255)
      ->truncateString((string)$author));

    $data->setCommitDetail('authorEpoch', $ref->getAuthorEpoch());
    $data->setCommitDetail('authorName', $ref->getAuthorName());
    $data->setCommitDetail('authorEmail', $ref->getAuthorEmail());

    $data->setCommitDetail(
      'authorPHID',
      $this->resolveUserPHID($commit, $author));

    $data->setCommitMessage($message);

    if (strlen($committer)) {
      $data->setCommitDetail('committer', $committer);

      $data->setCommitDetail('committerName', $ref->getCommitterName());
      $data->setCommitDetail('committerEmail', $ref->getCommitterEmail());

      $data->setCommitDetail(
        'committerPHID',
        $this->resolveUserPHID($commit, $committer));
    }

    $repository = $this->repository;

    $author_phid = $data->getCommitDetail('authorPHID');
    $committer_phid = $data->getCommitDetail('committerPHID');

    $user = new PhabricatorUser();
    if ($author_phid) {
      $user = $user->loadOneWhere(
        'phid = %s',
        $author_phid);
    }

    $differential_app = 'PhabricatorDifferentialApplication';
    $revision_id = null;
    $low_level_query = null;
    if (PhabricatorApplication::isClassInstalled($differential_app)) {
      $low_level_query = id(new DiffusionLowLevelCommitFieldsQuery())
        ->setRepository($repository)
        ->withCommitRef($ref);
      $field_values = $low_level_query->execute();
      $revision_id = idx($field_values, 'revisionID');

      if (!empty($field_values['reviewedByPHIDs'])) {
        $data->setCommitDetail(
          'reviewerPHID',
          reset($field_values['reviewedByPHIDs']));
      }

      $data->setCommitDetail('differential.revisionID', $revision_id);
    }

    if ($author_phid != $commit->getAuthorPHID()) {
      $commit->setAuthorPHID($author_phid);
    }

    $commit->setSummary($data->getSummary());
    $commit->save();

    // Figure out if we're going to try to "autoclose" related objects (e.g.,
    // close linked tasks and related revisions) and, if not, record why we
    // aren't. Autoclose can be disabled for various reasons at the repository
    // or commit levels.

    $force_autoclose = idx($this->getTaskData(), 'forceAutoclose', false);
    if ($force_autoclose) {
      $autoclose_reason = PhabricatorRepository::BECAUSE_AUTOCLOSE_FORCED;
    } else {
      $autoclose_reason = $repository->shouldSkipAutocloseCommit($commit);
    }
    $data->setCommitDetail('autocloseReason', $autoclose_reason);
    $should_autoclose = $force_autoclose ||
                        $repository->shouldAutocloseCommit($commit);


    // When updating related objects, we'll act under an omnipotent user to
    // ensure we can see them, but take actions as either the committer or
    // author (if we recognize their accounts) or the Diffusion application
    // (if we do not).

    $actor = PhabricatorUser::getOmnipotentUser();
    $acting_as_phid = nonempty(
      $committer_phid,
      $author_phid,
      id(new PhabricatorDiffusionApplication())->getPHID());

    $conn_w = id(new DifferentialRevision())->establishConnection('w');

    // NOTE: The `differential_commit` table has a unique ID on `commitPHID`,
    // preventing more than one revision from being associated with a commit.
    // Generally this is good and desirable, but with the advent of hash
    // tracking we may end up in a situation where we match several different
    // revisions. We just kind of ignore this and pick one, we might want to
    // revisit this and do something differently. (If we match several revisions
    // someone probably did something very silly, though.)

    $revision = null;
    if ($revision_id) {
      $revision_query = id(new DifferentialRevisionQuery())
        ->withIDs(array($revision_id))
        ->setViewer($actor)
        ->needReviewerStatus(true)
        ->needActiveDiffs(true);

      $revision = $revision_query->executeOne();

      if ($revision) {
        if (!$data->getCommitDetail('precommitRevisionStatus')) {
          $data->setCommitDetail(
            'precommitRevisionStatus',
            $revision->getStatus());
        }
        $commit_drev = DiffusionCommitHasRevisionEdgeType::EDGECONST;
        id(new PhabricatorEdgeEditor())
          ->addEdge($commit->getPHID(), $commit_drev, $revision->getPHID())
          ->save();

        queryfx(
          $conn_w,
          'INSERT IGNORE INTO %T (revisionID, commitPHID) VALUES (%d, %s)',
          DifferentialRevision::TABLE_COMMIT,
          $revision->getID(),
          $commit->getPHID());

        $status_closed = ArcanistDifferentialRevisionStatus::CLOSED;
        $should_close = ($revision->getStatus() != $status_closed) &&
                        $should_autoclose;

        if ($should_close) {
           $commit_close_xaction = id(new DifferentialTransaction())
            ->setTransactionType(DifferentialTransaction::TYPE_ACTION)
            ->setNewValue(DifferentialAction::ACTION_CLOSE)
            ->setMetadataValue('isCommitClose', true);

          $commit_close_xaction->setMetadataValue(
            'commitPHID',
            $commit->getPHID());
          $commit_close_xaction->setMetadataValue(
            'committerPHID',
            $committer_phid);
          $commit_close_xaction->setMetadataValue(
            'committerName',
            $data->getCommitDetail('committer'));
          $commit_close_xaction->setMetadataValue(
            'authorPHID',
            $author_phid);
          $commit_close_xaction->setMetadataValue(
            'authorName',
            $data->getAuthorName());

          if ($low_level_query) {
            $commit_close_xaction->setMetadataValue(
              'revisionMatchData',
              $low_level_query->getRevisionMatchData());
            $data->setCommitDetail(
              'revisionMatchData',
              $low_level_query->getRevisionMatchData());
          }

          $extraction_engine = id(new DifferentialDiffExtractionEngine())
            ->setViewer($actor)
            ->setAuthorPHID($acting_as_phid);

          $content_source = $this->newContentSource();

          $update_data = $extraction_engine->updateRevisionWithCommit(
            $revision,
            $commit,
            array(
              $commit_close_xaction,
            ),
            $content_source);

          foreach ($update_data as $key => $value) {
            $data->setCommitDetail($key, $value);
          }
        }
      }
    }

    if ($should_autoclose) {
      $this->closeTasks(
        $actor,
        $acting_as_phid,
        $repository,
        $commit,
        $message);
    }

    $data->save();

    $commit->writeImportStatusFlag(
      PhabricatorRepositoryCommit::IMPORTED_MESSAGE);
  }

  private function resolveUserPHID(
    PhabricatorRepositoryCommit $commit,
    $user_name) {

    return id(new DiffusionResolveUserQuery())
      ->withCommit($commit)
      ->withName($user_name)
      ->execute();
  }

  private function closeTasks(
    PhabricatorUser $actor,
    $acting_as,
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    $message) {

    $maniphest = 'PhabricatorManiphestApplication';
    if (!PhabricatorApplication::isClassInstalled($maniphest)) {
      return;
    }

    $prefixes = ManiphestTaskStatus::getStatusPrefixMap();
    $suffixes = ManiphestTaskStatus::getStatusSuffixMap();

    $matches = id(new ManiphestCustomFieldStatusParser())
      ->parseCorpus($message);

    $task_statuses = array();
    foreach ($matches as $match) {
      $prefix = phutil_utf8_strtolower($match['prefix']);
      $suffix = phutil_utf8_strtolower($match['suffix']);

      $status = idx($suffixes, $suffix);
      if (!$status) {
        $status = idx($prefixes, $prefix);
      }

      foreach ($match['monograms'] as $task_monogram) {
        $task_id = (int)trim($task_monogram, 'tT');
        $task_statuses[$task_id] = $status;
      }
    }

    if (!$task_statuses) {
      return;
    }

    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($actor)
      ->withIDs(array_keys($task_statuses))
      ->needProjectPHIDs(true)
      ->execute();

    foreach ($tasks as $task_id => $task) {
      $xactions = array();

      $edge_type = ManiphestTaskHasCommitEdgeType::EDGECONST;
      $edge_xaction = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $edge_type)
        ->setNewValue(
          array(
            '+' => array(
              $commit->getPHID() => $commit->getPHID(),
            ),
          ));

      $status = $task_statuses[$task_id];
      if ($status) {
        if ($task->getStatus() != $status) {
          $xactions[] = id(new ManiphestTransaction())
            ->setTransactionType(ManiphestTransaction::TYPE_STATUS)
            ->setMetadataValue('commitPHID', $commit->getPHID())
            ->setNewValue($status);

          $edge_xaction->setMetadataValue('commitPHID', $commit->getPHID());
        }
      }

      $xactions[] = $edge_xaction;

      $content_source = $this->newContentSource();

      $editor = id(new ManiphestTransactionEditor())
        ->setActor($actor)
        ->setActingAsPHID($acting_as)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setUnmentionablePHIDMap(
          array($commit->getPHID() => $commit->getPHID()))
        ->setContentSource($content_source);

      $editor->applyTransactions($task, $xactions);
    }
  }

}
