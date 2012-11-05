<?php

abstract class PhabricatorRepositoryCommitMessageParserWorker
  extends PhabricatorRepositoryCommitParserWorker {

  abstract protected function getCommitHashes(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit);

  final protected function updateCommitData($author, $message,
    $committer = null) {

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

    if ($committer) {
      $data->setCommitDetail('committer', $committer);
    }

    $repository = $this->repository;
    $detail_parser = $repository->getDetail(
      'detail-parser',
      'PhabricatorRepositoryDefaultCommitMessageDetailParser');

    if ($detail_parser) {
      $parser_obj = newv($detail_parser, array($commit, $data));
      $parser_obj->parseCommitDetails();
    }

    $author_phid = $this->lookupUser(
      $commit,
      $data->getAuthorName(),
      $data->getCommitDetail('authorPHID'));
    $data->setCommitDetail('authorPHID', $author_phid);

    $committer_phid = $this->lookupUser(
      $commit,
      $data->getCommitDetail('committer'),
      $data->getCommitDetail('committerPHID'));
    $data->setCommitDetail('committerPHID', $committer_phid);

    if ($author_phid != $commit->getAuthorPHID()) {
      $commit->setAuthorPHID($author_phid);
      $commit->save();
    }

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
      $lock = PhabricatorGlobalLock::newLock(get_class($this).':'.$revision_id);
      $lock->lock(5 * 60);

      $revision = id(new DifferentialRevision())->load($revision_id);
      if ($revision) {
        $revision->loadRelationships();
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
            $data->getCommitDetail('committer'));

          $author_name = $this->loadUserName(
            $author_phid,
            $data->getAuthorName());

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

    if ($should_autoclose && $author_phid) {
      $user = id(new PhabricatorUser())->loadOneWhere(
        'phid = %s',
        $author_phid);

      $call = new ConduitCall(
        'differential.parsecommitmessage',
        array(
          'corpus' => $message,
          'partial' => true,
        ));
      $call->setUser($user);
      $result = $call->execute();

      $field_values = $result['fields'];

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
  }

  private function loadUserName($user_phid, $default) {
    if (!$user_phid) {
      return $default;
    }
    $handle = PhabricatorObjectHandleData::loadOneHandle($user_phid);
    return '@'.$handle->getName();
  }

  private function attachToRevision(
    DifferentialRevision $revision,
    $actor_phid) {

    $drequest = DiffusionRequest::newFromDictionary(array(
      'repository' => $this->repository,
      'commit' => $this->commit->getCommitIdentifier(),
    ));

    $raw_diff = DiffusionRawDiffQuery::newFromDiffusionRequest($drequest)
      ->loadRawDiff();

    // TODO: Support adds, deletes and moves under SVN.
    $changes = id(new ArcanistDiffParser())->parseDiff($raw_diff);
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

    $parents = DiffusionCommitParentsQuery::newFromDiffusionRequest($drequest)
      ->loadParents();
    if ($parents) {
      $diff->setSourceControlBaseRevision(head_key($parents));
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
      $files = id(new PhabricatorFile())->loadAllWhere(
        'phid IN (%Ls)',
        $file_phids);
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
          'repository' => $this->repository,
          'commit' => $this->commit->getCommitIdentifier(),
          'path' => $path,
        ));
        $corpus = DiffusionFileContentQuery::newFromDiffusionRequest($drequest)
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

  /**
   * When querying for revisions by hash, more than one revision may be found.
   * This function identifies the "best" revision from such a set.  Typically,
   * there is only one revision found.   Otherwise, we try to pick an accepted
   * revision first, followed by an open revision, and otherwise we go with a
   * closed or abandoned revision as a last resort.
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
        case ArcanistDifferentialRevisionStatus::CLOSED:
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

  /**
   * Emit an event so installs can do custom lookup of commit authors who may
   * not be naturally resolvable.
   */
  private function lookupUser(
    PhabricatorRepositoryCommit $commit,
    $query,
    $guess) {

    $type = PhabricatorEventType::TYPE_DIFFUSION_LOOKUPUSER;
    $data = array(
      'commit'  => $commit,
      'query'   => $query,
      'result'  => $guess,
    );

    $event = new PhabricatorEvent($type, $data);
    PhutilEventEngine::dispatchEvent($event);

    return $event->getValue('result');
  }

}
