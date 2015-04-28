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

          $diff = $this->generateFinalDiff($revision, $acting_as_phid);

          $vs_diff = $this->loadChangedByCommit($revision, $diff);
          $changed_uri = null;
          if ($vs_diff) {
            $data->setCommitDetail('vsDiff', $vs_diff->getID());

            $changed_uri = PhabricatorEnv::getProductionURI(
              '/D'.$revision->getID().
              '?vs='.$vs_diff->getID().
              '&id='.$diff->getID().
              '#toc');
          }

          $xactions = array();
          $xactions[] = id(new DifferentialTransaction())
            ->setTransactionType(DifferentialTransaction::TYPE_UPDATE)
            ->setIgnoreOnNoEffect(true)
            ->setNewValue($diff->getPHID())
            ->setMetadataValue('isCommitUpdate', true);
          $xactions[] = $commit_close_xaction;

          $content_source = PhabricatorContentSource::newForSource(
            PhabricatorContentSource::SOURCE_DAEMON,
            array());

          $editor = id(new DifferentialTransactionEditor())
            ->setActor($actor)
            ->setActingAsPHID($acting_as_phid)
            ->setContinueOnMissingFields(true)
            ->setContentSource($content_source)
            ->setChangedPriorToCommitURI($changed_uri)
            ->setIsCloseByCommit(true);

          try {
            $editor->applyTransactions($revision, $xactions);
          } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
            // NOTE: We've marked transactions other than the CLOSE transaction
            // as ignored when they don't have an effect, so this means that we
            // lost a race to close the revision. That's perfectly fine, we can
            // just continue normally.
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

  private function generateFinalDiff(
    DifferentialRevision $revision,
    $actor_phid) {

    $viewer = PhabricatorUser::getOmnipotentUser();

    $drequest = DiffusionRequest::newFromDictionary(array(
      'user' => $viewer,
      'repository' => $this->repository,
    ));

    $raw_diff = DiffusionQuery::callConduitWithDiffusionRequest(
      $viewer,
      $drequest,
      'diffusion.rawdiffquery',
      array(
        'commit' => $this->commit->getCommitIdentifier(),
      ));

    // TODO: Support adds, deletes and moves under SVN.
    if (strlen($raw_diff)) {
      $changes = id(new ArcanistDiffParser())->parseDiff($raw_diff);
    } else {
      // This is an empty diff, maybe made with `git commit --allow-empty`.
      // NOTE: These diffs have the same tree hash as their ancestors, so
      // they may attach to revisions in an unexpected way. Just let this
      // happen for now, although it might make sense to special case it
      // eventually.
      $changes = array();
    }

    $diff = DifferentialDiff::newFromRawChanges($viewer, $changes)
      ->setRepositoryPHID($this->repository->getPHID())
      ->setAuthorPHID($actor_phid)
      ->setCreationMethod('commit')
      ->setSourceControlSystem($this->repository->getVersionControlSystem())
      ->setLintStatus(DifferentialLintStatus::LINT_AUTO_SKIP)
      ->setUnitStatus(DifferentialUnitStatus::UNIT_AUTO_SKIP)
      ->setDateCreated($this->commit->getEpoch())
      ->setDescription(
        'Commit r'.
        $this->repository->getCallsign().
        $this->commit->getCommitIdentifier());

    // TODO: This is not correct in SVN where one repository can have multiple
    // Arcanist projects.
    $arcanist_project = id(new PhabricatorRepositoryArcanistProject())
      ->loadOneWhere('repositoryID = %d LIMIT 1', $this->repository->getID());
    if ($arcanist_project) {
      $diff->setArcanistProjectPHID($arcanist_project->getPHID());
    }

    $parents = DiffusionQuery::callConduitWithDiffusionRequest(
      $viewer,
      $drequest,
      'diffusion.commitparentsquery',
      array(
        'commit' => $this->commit->getCommitIdentifier(),
      ));
    if ($parents) {
      $diff->setSourceControlBaseRevision(head($parents));
    }

    // TODO: Attach binary files.

    return $diff->save();
  }

  private function loadChangedByCommit(
    DifferentialRevision $revision,
    DifferentialDiff $diff) {

    $repository = $this->repository;

    $vs_diff = id(new DifferentialDiffQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withRevisionIDs(array($revision->getID()))
      ->needChangesets(true)
      ->setLimit(1)
      ->executeOne();
    if (!$vs_diff) {
      return null;
    }

    if ($vs_diff->getCreationMethod() == 'commit') {
      return null;
    }

    $vs_changesets = array();
    foreach ($vs_diff->getChangesets() as $changeset) {
      $path = $changeset->getAbsoluteRepositoryPath($repository, $vs_diff);
      $path = ltrim($path, '/');
      $vs_changesets[$path] = $changeset;
    }

    $changesets = array();
    foreach ($diff->getChangesets() as $changeset) {
      $path = $changeset->getAbsoluteRepositoryPath($repository, $diff);
      $path = ltrim($path, '/');
      $changesets[$path] = $changeset;
    }

    if (array_fill_keys(array_keys($changesets), true) !=
        array_fill_keys(array_keys($vs_changesets), true)) {
      return $vs_diff;
    }

    $file_phids = array();
    foreach ($vs_changesets as $changeset) {
      $metadata = $changeset->getMetadata();
      $file_phid = idx($metadata, 'new:binary-phid');
      if ($file_phid) {
        $file_phids[$file_phid] = $file_phid;
      }
    }

    $files = array();
    if ($file_phids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs($file_phids)
        ->execute();
      $files = mpull($files, null, 'getPHID');
    }

    foreach ($changesets as $path => $changeset) {
      $vs_changeset = $vs_changesets[$path];

      $file_phid = idx($vs_changeset->getMetadata(), 'new:binary-phid');
      if ($file_phid) {
        if (!isset($files[$file_phid])) {
          return $vs_diff;
        }
        $drequest = DiffusionRequest::newFromDictionary(array(
          'user' => PhabricatorUser::getOmnipotentUser(),
          'repository' => $this->repository,
          'commit' => $this->commit->getCommitIdentifier(),
          'path' => $path,
        ));
        $corpus = DiffusionFileContentQuery::newFromDiffusionRequest($drequest)
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->loadFileContent()
          ->getCorpus();
        if ($files[$file_phid]->loadFileData() != $corpus) {
          return $vs_diff;
        }
      } else {
        $context = implode("\n", $changeset->makeChangesWithContext());
        $vs_context = implode("\n", $vs_changeset->makeChangesWithContext());

        // We couldn't just compare $context and $vs_context because following
        // diffs will be considered different:
        //
        //   -(empty line)
        //   -echo 'test';
        //    (empty line)
        //
        //    (empty line)
        //   -echo "test";
        //   -(empty line)

        $hunk = id(new DifferentialModernHunk())->setChanges($context);
        $vs_hunk = id(new DifferentialModernHunk())->setChanges($vs_context);
        if ($hunk->makeOldFile() != $vs_hunk->makeOldFile() ||
            $hunk->makeNewFile() != $vs_hunk->makeNewFile()) {
          return $vs_diff;
        }
      }
    }

    return null;
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

      $content_source = PhabricatorContentSource::newForSource(
        PhabricatorContentSource::SOURCE_DAEMON,
        array());

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
