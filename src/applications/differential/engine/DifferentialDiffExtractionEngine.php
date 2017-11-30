<?php

final class DifferentialDiffExtractionEngine extends Phobject {

  private $viewer;
  private $authorPHID;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setAuthorPHID($author_phid) {
    $this->authorPHID = $author_phid;
    return $this;
  }

  public function getAuthorPHID() {
    return $this->authorPHID;
  }

  public function newDiffFromCommit(PhabricatorRepositoryCommit $commit) {
    $viewer = $this->getViewer();

    // If we already have an unattached diff for this commit, just reuse it.
    // This stops us from repeatedly generating diffs if something goes wrong
    // later in the process. See T10968 for context.
    $existing_diffs = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withCommitPHIDs(array($commit->getPHID()))
      ->withHasRevision(false)
      ->needChangesets(true)
      ->execute();
    if ($existing_diffs) {
      return head($existing_diffs);
    }

    $repository = $commit->getRepository();
    $identifier = $commit->getCommitIdentifier();
    $monogram = $commit->getMonogram();

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => $viewer,
        'repository' => $repository,
      ));

    $diff_info = DiffusionQuery::callConduitWithDiffusionRequest(
      $viewer,
      $drequest,
      'diffusion.rawdiffquery',
      array(
        'commit' => $identifier,
      ));

    $file_phid = $diff_info['filePHID'];
    $diff_file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$diff_file) {
      throw new Exception(
        pht(
          'Failed to load file ("%s") returned by "%s".',
          $file_phid,
          'diffusion.rawdiffquery'));
    }

    $raw_diff = $diff_file->loadFileData();

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
      ->setRepositoryPHID($repository->getPHID())
      ->setCommitPHID($commit->getPHID())
      ->setCreationMethod('commit')
      ->setSourceControlSystem($repository->getVersionControlSystem())
      ->setLintStatus(DifferentialLintStatus::LINT_AUTO_SKIP)
      ->setUnitStatus(DifferentialUnitStatus::UNIT_AUTO_SKIP)
      ->setDateCreated($commit->getEpoch())
      ->setDescription($monogram);

    $author_phid = $this->getAuthorPHID();
    if ($author_phid !== null) {
      $diff->setAuthorPHID($author_phid);
    }

    $parents = DiffusionQuery::callConduitWithDiffusionRequest(
      $viewer,
      $drequest,
      'diffusion.commitparentsquery',
      array(
        'commit' => $identifier,
      ));

    if ($parents) {
      $diff->setSourceControlBaseRevision(head($parents));
    }

    // TODO: Attach binary files.

    return $diff->save();
  }

  public function isDiffChangedBeforeCommit(
    PhabricatorRepositoryCommit $commit,
    DifferentialDiff $old,
    DifferentialDiff $new) {

    $viewer = $this->getViewer();
    $repository = $commit->getRepository();
    $identifier = $commit->getCommitIdentifier();

    $vs_changesets = array();
    foreach ($old->getChangesets() as $changeset) {
      $path = $changeset->getAbsoluteRepositoryPath($repository, $old);
      $path = ltrim($path, '/');
      $vs_changesets[$path] = $changeset;
    }

    $changesets = array();
    foreach ($new->getChangesets() as $changeset) {
      $path = $changeset->getAbsoluteRepositoryPath($repository, $new);
      $path = ltrim($path, '/');
      $changesets[$path] = $changeset;
    }

    if (array_fill_keys(array_keys($changesets), true) !=
        array_fill_keys(array_keys($vs_changesets), true)) {
      return true;
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
          return true;
        }

        $drequest = DiffusionRequest::newFromDictionary(
          array(
            'user' => $viewer,
            'repository' => $repository,
          ));

        $response = DiffusionQuery::callConduitWithDiffusionRequest(
          $viewer,
          $drequest,
          'diffusion.filecontentquery',
          array(
            'commit' => $identifier,
            'path' => $path,
          ));

        $new_file_phid = $response['filePHID'];
        if (!$new_file_phid) {
          return true;
        }

        $new_file = id(new PhabricatorFileQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($new_file_phid))
          ->executeOne();
        if (!$new_file) {
          return true;
        }

        if ($files[$file_phid]->loadFileData() != $new_file->loadFileData()) {
          return true;
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
          return true;
        }
      }
    }

    return false;
  }

  public function updateRevisionWithCommit(
    DifferentialRevision $revision,
    PhabricatorRepositoryCommit $commit,
    array $more_xactions,
    PhabricatorContentSource $content_source) {

    $viewer = $this->getViewer();
    $result_data = array();

    $new_diff = $this->newDiffFromCommit($commit);

    $old_diff = $revision->getActiveDiff();
    $changed_uri = null;
    if ($old_diff) {
      $old_diff = id(new DifferentialDiffQuery())
        ->setViewer($viewer)
        ->withIDs(array($old_diff->getID()))
        ->needChangesets(true)
        ->executeOne();
      if ($old_diff) {
        $has_changed = $this->isDiffChangedBeforeCommit(
          $commit,
          $old_diff,
          $new_diff);
        if ($has_changed) {
          $result_data['vsDiff'] = $old_diff->getID();

          $revision_monogram = $revision->getMonogram();
          $old_id = $old_diff->getID();
          $new_id = $new_diff->getID();

          $changed_uri = "/{$revision_monogram}?vs={$old_id}&id={$new_id}#toc";
          $changed_uri = PhabricatorEnv::getProductionURI($changed_uri);
        }
      }
    }

    $xactions = array();

    // If the revision isn't closed or "Accepted", write a warning into the
    // transaction log. This makes it more clear when users bend the rules.
    if (!$revision->isClosed() && !$revision->isAccepted()) {
      $wrong_type = DifferentialRevisionWrongStateTransaction::TRANSACTIONTYPE;

      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType($wrong_type)
        ->setNewValue($revision->getModernRevisionStatus());
    }

    $xactions[] = id(new DifferentialTransaction())
      ->setTransactionType(DifferentialTransaction::TYPE_UPDATE)
      ->setIgnoreOnNoEffect(true)
      ->setNewValue($new_diff->getPHID())
      ->setMetadataValue('isCommitUpdate', true);

    foreach ($more_xactions as $more_xaction) {
      $xactions[] = $more_xaction;
    }

    $editor = id(new DifferentialTransactionEditor())
      ->setActor($viewer)
      ->setContinueOnMissingFields(true)
      ->setContentSource($content_source)
      ->setChangedPriorToCommitURI($changed_uri)
      ->setIsCloseByCommit(true);

    $author_phid = $this->getAuthorPHID();
    if ($author_phid !== null) {
      $editor->setActingAsPHID($author_phid);
    }

    try {
      $editor->applyTransactions($revision, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      // NOTE: We've marked transactions other than the CLOSE transaction
      // as ignored when they don't have an effect, so this means that we
      // lost a race to close the revision. That's perfectly fine, we can
      // just continue normally.
    }

    return $result_data;
  }

}
