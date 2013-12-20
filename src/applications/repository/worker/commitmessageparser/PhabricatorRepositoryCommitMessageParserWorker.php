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
      $lock = PhabricatorGlobalLock::newLock(get_class($this).':'.$revision_id);
      $lock->lock(5 * 60);

      // TODO: Check if a more restrictive viewer could be set here
      $revision = id(new DifferentialRevisionQuery())
        ->withIDs(array($revision_id))
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->needRelationships(true)
        ->needReviewerStatus(true)
        ->executeOne();

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

          $diff = $this->attachToRevision($revision, $actor_phid);

          $revision->setDateCommitted($commit->getEpoch());
          $editor = new DifferentialCommentEditor(
            $revision,
            DifferentialAction::ACTION_CLOSE);
          $editor->setActor($actor);
          $editor->setIsDaemonWorkflow(true);

          $vs_diff = $this->loadChangedByCommit($diff);
          if ($vs_diff) {
            $data->setCommitDetail('vsDiff', $vs_diff->getID());

            $changed_by_commit = PhabricatorEnv::getProductionURI(
              '/D'.$revision->getID().
              '?vs='.$vs_diff->getID().
              '&id='.$diff->getID().
              '#toc');
            $editor->setChangedByCommit($changed_by_commit);
          }

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

          $info = array();
          $info[] = "authored by {$author_name}";
          if ($committer_name && ($committer_name != $author_name)) {
            $info[] = "committed by {$committer_name}";
          }
          $info = implode(', ', $info);

          $editor
            ->setMessage("Closed by commit {$commit_name} ({$info}).")
            ->save();
        }

      }

      $lock->unlock();
    }

    if ($should_autoclose) {
      $fields = DifferentialFieldSelector::newSelector()
        ->getFieldSpecifications();
      foreach ($fields as $key => $field) {
        if (!$field->shouldAppearOnCommitMessage()) {
          continue;
        }
        $field->setUser($user);
        $value = idx($field_values, $field->getCommitMessageKey());
        $field->setValueFromParsedCommitMessage($value);
        if ($revision) {
          $field->setRevision($revision);
        }
        $field->didParseCommit($repository, $commit, $data);
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

  private function attachToRevision(
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
      ->setRevisionID($revision->getID())
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

    $revision->setLineCount($diff->getLineCount());

    return $diff->save();
  }

  private function loadChangedByCommit(DifferentialDiff $diff) {
    $repository = $this->repository;

    $vs_changesets = array();
    $vs_diff = id(new DifferentialDiff())->loadOneWhere(
      'revisionID = %d AND creationMethod != %s ORDER BY id DESC LIMIT 1',
      $diff->getRevisionID(),
      'commit');
    foreach ($vs_diff->loadChangesets() as $changeset) {
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

    $hunks = id(new DifferentialHunk())->loadAllWhere(
      'changesetID IN (%Ld)',
      mpull($vs_changesets, 'getID'));
    $hunks = mgroup($hunks, 'getChangesetID');
    foreach ($vs_changesets as $changeset) {
      $changeset->attachHunks(idx($hunks, $changeset->getID(), array()));
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

        $hunk = id(new DifferentialHunk())->setChanges($context);
        $vs_hunk = id(new DifferentialHunk())->setChanges($vs_context);
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

}
