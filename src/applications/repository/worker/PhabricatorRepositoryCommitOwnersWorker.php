<?php

final class PhabricatorRepositoryCommitOwnersWorker
  extends PhabricatorRepositoryCommitParserWorker {

  protected function getImportStepFlag() {
    return PhabricatorRepositoryCommit::IMPORTED_OWNERS;
  }

  protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    if (!$this->shouldSkipImportStep()) {
      $this->triggerOwnerAudits($repository, $commit);
      $commit->writeImportStatusFlag($this->getImportStepFlag());
    }

    if ($this->shouldQueueFollowupTasks()) {
      $this->queueTask(
        'PhabricatorRepositoryCommitHeraldWorker',
        array(
          'commitID' => $commit->getID(),
        ));
    }
  }

  private function triggerOwnerAudits(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    if (!$repository->shouldPublish()) {
      return;
    }

    $affected_paths = PhabricatorOwnerPathQuery::loadAffectedPaths(
      $repository,
      $commit,
      PhabricatorUser::getOmnipotentUser());

    $affected_packages = PhabricatorOwnersPackage::loadAffectedPackages(
      $repository,
      $affected_paths);

    $commit->writeOwnersEdges(mpull($affected_packages, 'getPHID'));

    if (!$affected_packages) {
      return;
    }

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($commit->getPHID()))
      ->needCommitData(true)
      ->needAuditRequests(true)
      ->executeOne();
    if (!$commit) {
      return;
    }

    $data = $commit->getCommitData();

    $author_phid = $data->getCommitDetail('authorPHID');
    $revision_id = $data->getCommitDetail('differential.revisionID');
    if ($revision_id) {
      $revision = id(new DifferentialRevisionQuery())
        ->setViewer($viewer)
        ->withIDs(array($revision_id))
        ->needReviewerStatus(true)
        ->executeOne();
    } else {
      $revision = null;
    }

    $requests = $commit->getAudits();
    $requests = mpull($requests, null, 'getAuditorPHID');

    $auditor_phids = array();
    foreach ($affected_packages as $package) {
      $request = idx($requests, $package->getPHID());
      if ($request) {
        // Don't update request if it exists already.
        continue;
      }

      $should_audit = $this->shouldTriggerAudit(
        $commit,
        $package,
        $author_phid,
        $revision);
      if (!$should_audit) {
        continue;
      }

      $auditor_phids[] = $package->getPHID();
    }

    // If none of the packages are triggering audits, we're all done.
    if (!$auditor_phids) {
      return;
    }

    $audit_type = DiffusionCommitAuditorsTransaction::TRANSACTIONTYPE;

    $owners_phid = id(new PhabricatorOwnersApplication())
      ->getPHID();

    $content_source = $this->newContentSource();

    $xactions = array();
    $xactions[] = $commit->getApplicationTransactionTemplate()
      ->setTransactionType($audit_type)
      ->setNewValue(
        array(
          '+' => array_fuse($auditor_phids),
        ));

    $editor = $commit->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setActingAsPHID($owners_phid)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->setContentSource($content_source);

    $editor->applyTransactions($commit, $xactions);
  }

  private function shouldTriggerAudit(
    PhabricatorRepositoryCommit $commit,
    PhabricatorOwnersPackage $package,
    $author_phid,
    $revision) {

    // Don't trigger an audit if auditing isn't enabled for the package.
    if (!$package->getAuditingEnabled()) {
      return false;
    }

    // Trigger an audit if we don't recognize the commit's author.
    if (!$author_phid) {
      return true;
    }

    $owner_phids = PhabricatorOwnersOwner::loadAffiliatedUserPHIDs(
      array(
        $package->getID(),
      ));
    $owner_phids = array_fuse($owner_phids);

    // Don't trigger an audit if the author is a package owner.
    if (isset($owner_phids[$author_phid])) {
      return false;
    }

    // Trigger an audit of there is no corresponding revision.
    if (!$revision) {
      return true;
    }

    $accepted_statuses = array(
      DifferentialReviewerStatus::STATUS_ACCEPTED,
      DifferentialReviewerStatus::STATUS_ACCEPTED_OLDER,
    );
    $accepted_statuses = array_fuse($accepted_statuses);

    $found_accept = false;
    foreach ($revision->getReviewerStatus() as $reviewer) {
      $reviewer_phid = $reviewer->getReviewerPHID();

      // If this reviewer isn't a package owner, just ignore them.
      if (empty($owner_phids[$reviewer_phid])) {
        continue;
      }

      // If this reviewer accepted the revision and owns the package, we're
      // all clear and do not need to trigger an audit.
      if (isset($accepted_statuses[$reviewer->getStatus()])) {
        $found_accept = true;
        break;
      }
    }

    // Don't trigger an audit if a package owner already reviewed the
    // revision.
    if ($found_accept) {
      return false;
    }

    return true;
  }

}
