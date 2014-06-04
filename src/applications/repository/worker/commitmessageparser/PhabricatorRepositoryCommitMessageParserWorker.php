<?php

abstract class PhabricatorRepositoryCommitMessageParserWorker
  extends PhabricatorRepositoryCommitParserWorker {

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
    $data->setAuthorName($author);
    $data->setCommitDetail(
      'authorPHID',
      $this->resolveUserPHID($commit, $author));

    $data->setCommitMessage($message);

    if (strlen($committer)) {
      $data->setCommitDetail('committer', $committer);
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

    $field_values = id(new DiffusionLowLevelCommitFieldsQuery())
      ->setRepository($repository)
      ->withCommitRef($ref)
      ->execute();
    $revision_id = idx($field_values, 'revisionID');

    if (!empty($field_values['reviewedByPHIDs'])) {
      $data->setCommitDetail(
        'reviewerPHID',
        reset($field_values['reviewedByPHIDs']));
    }

    $data->setCommitDetail('differential.revisionID', $revision_id);

    if ($author_phid != $commit->getAuthorPHID()) {
      $commit->setAuthorPHID($author_phid);
    }

    $commit->setSummary($data->getSummary());
    $commit->save();

    $conn_w = id(new DifferentialRevision())->establishConnection('w');

    // NOTE: The `differential_commit` table has a unique ID on `commitPHID`,
    // preventing more than one revision from being associated with a commit.
    // Generally this is good and desirable, but with the advent of hash
    // tracking we may end up in a situation where we match several different
    // revisions. We just kind of ignore this and pick one, we might want to
    // revisit this and do something differently. (If we match several revisions
    // someone probably did something very silly, though.)

    $revision = null;
    $should_autoclose = $repository->shouldAutocloseCommit($commit, $data);

    if ($revision_id) {
      // TODO: Check if a more restrictive viewer could be set here
      $revision_query = id(new DifferentialRevisionQuery())
        ->withIDs(array($revision_id))
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->needReviewerStatus(true)
        ->needActiveDiffs(true);

      $revision = $revision_query->executeOne();

      if ($revision) {
        $commit_drev = PhabricatorEdgeConfig::TYPE_COMMIT_HAS_DREV;
        id(new PhabricatorEdgeEditor())
          ->setActor($user)
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
          $actor_phid = nonempty(
            $committer_phid,
            $author_phid,
            $revision->getAuthorPHID());

          $actor = id(new PhabricatorUser())
            ->loadOneWhere('phid = %s', $actor_phid);

          $commit_name = $repository->formatCommitName(
            $commit->getCommitIdentifier());

          $committer_name = $this->loadUserName(
            $committer_phid,
            $data->getCommitDetail('committer'),
            $actor);

          $author_name = $this->loadUserName(
            $author_phid,
            $data->getAuthorName(),
            $actor);

          if ($committer_name && ($committer_name != $author_name)) {
            $revision_update_comment = pht(
              'Closed by commit %s (authored by %s, committed by %s).',
              $commit_name,
              $author_name,
              $committer_name);
          } else {
            $revision_update_comment = pht(
              'Closed by commit %s (authored by %s).',
              $commit_name,
              $author_name);
          }

          $diff = $this->generateFinalDiff($revision, $actor_phid);

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
            ->setTransactionType(DifferentialTransaction::TYPE_ACTION)
            ->setNewValue(DifferentialAction::ACTION_CLOSE);

          $xactions[] = id(new DifferentialTransaction())
            ->setTransactionType(DifferentialTransaction::TYPE_UPDATE)
            ->setIgnoreOnNoEffect(true)
            ->setNewValue($diff->getPHID());

          $xactions[] = id(new DifferentialTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
            ->setIgnoreOnNoEffect(true)
            ->attachComment(
              id(new DifferentialTransactionComment())
                ->setContent($revision_update_comment));

          $content_source = PhabricatorContentSource::newForSource(
            PhabricatorContentSource::SOURCE_DAEMON,
            array());

          $editor = id(new DifferentialTransactionEditor())
            ->setActor($actor)
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
      // TODO: This isn't as general as it could be.
      if ($user->getPHID()) {
        $this->closeTasks($user, $repository, $commit, $message);
      }
    }

    $data->save();

    $commit->writeImportStatusFlag(
      PhabricatorRepositoryCommit::IMPORTED_MESSAGE);
  }

  private function loadUserName($user_phid, $default, PhabricatorUser $actor) {
    if (!$user_phid) {
      return $default;
    }
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($actor)
      ->withPHIDs(array($user_phid))
      ->executeOne();

    return '@'.$handle->getName();
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

    $diff = DifferentialDiff::newFromRawChanges($changes)
      ->setRepositoryPHID($this->repository->getPHID())
      ->setAuthorPHID($actor_phid)
      ->setCreationMethod('commit')
      ->setSourceControlSystem($this->repository->getVersionControlSystem())
      ->setLintStatus(DifferentialLintStatus::LINT_SKIP)
      ->setUnitStatus(DifferentialUnitStatus::UNIT_SKIP)
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
          'initFromConduit' => false,
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

        $hunk = id(new DifferentialHunkModern())->setChanges($context);
        $vs_hunk = id(new DifferentialHunkModern())->setChanges($vs_context);
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
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    $message) {

    $maniphest = 'PhabricatorApplicationManiphest';
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
      ->execute();

    foreach ($tasks as $task_id => $task) {
      $xactions = array();

      // TODO: Swap this for a real edge transaction once the weirdness in
      // Maniphest edges is sorted out. Currently, Maniphest reacts to an edge
      // edit on this edge.
      id(new PhabricatorEdgeEditor())
        ->setActor($actor)
        ->addEdge(
          $task->getPHID(),
          PhabricatorEdgeConfig::TYPE_TASK_HAS_COMMIT,
          $commit->getPHID())
        ->save();

      /* TODO: Do this instead of the above.

      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $edge_task_has_commit)
        ->setNewValue(
          array(
            '+' => array(
              $commit->getPHID() => $commit->getPHID(),
            ),
          ));
      */

      $status = $task_statuses[$task_id];
      if ($status) {
        if ($task->getStatus() != $status) {
          $xactions[] = id(new ManiphestTransaction())
            ->setTransactionType(ManiphestTransaction::TYPE_STATUS)
            ->setNewValue($status);

          $commit_name = $repository->formatCommitName(
            $commit->getCommitIdentifier());

          $status_message = pht(
            'Closed by commit %s.',
            $commit_name);

          $xactions[] = id(new ManiphestTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
            ->attachComment(
              id(new ManiphestTransactionComment())
                ->setContent($status_message));
        }
      }

      $content_source = PhabricatorContentSource::newForSource(
        PhabricatorContentSource::SOURCE_DAEMON,
        array());

      $editor = id(new ManiphestTransactionEditor())
        ->setActor($actor)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSource($content_source);

      $editor->applyTransactions($task, $xactions);
    }
  }

}
