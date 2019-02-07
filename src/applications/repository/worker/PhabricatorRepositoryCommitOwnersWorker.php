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
        ->needReviewers(true)
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

    $audit_uninvolved = false;
    $audit_unreviewed = false;

    $rule = $package->newAuditingRule();
    switch ($rule->getKey()) {
      case PhabricatorOwnersAuditRule::AUDITING_NONE:
        return false;
      case PhabricatorOwnersAuditRule::AUDITING_ALL:
        return true;
      case PhabricatorOwnersAuditRule::AUDITING_NO_OWNER:
        $audit_uninvolved = true;
        break;
      case PhabricatorOwnersAuditRule::AUDITING_UNREVIEWED:
        $audit_unreviewed = true;
        break;
      case PhabricatorOwnersAuditRule::AUDITING_NO_OWNER_AND_UNREVIEWED:
        $audit_uninvolved = true;
        $audit_unreviewed = true;
        break;
    }

    // If auditing is configured to trigger on unreviewed changes, check if
    // the revision was "Accepted" when it landed. If not, trigger an audit.
    if ($audit_unreviewed) {
      $commit_unreviewed = true;
      if ($revision) {
        $was_accepted = DifferentialRevision::PROPERTY_CLOSED_FROM_ACCEPTED;
        if ($revision->isPublished()) {
          if ($revision->getProperty($was_accepted)) {
            $commit_unreviewed = false;
          }
        }
      }

      if ($commit_unreviewed) {
        return true;
      }
    }

    // If auditing is configured to trigger on changes with no involved owner,
    // check for an owner. If we don't find one, trigger an audit.
    if ($audit_uninvolved) {
      $commit_uninvolved = $this->isOwnerInvolved(
        $commit,
        $package,
        $author_phid,
        $revision);
      if ($commit_uninvolved) {
        return true;
      }
    }

    // We can't find any reason to trigger an audit for this commit.
    return false;
  }

  private function isOwnerInvolved(
    PhabricatorRepositoryCommit $commit,
    PhabricatorOwnersPackage $package,
    $author_phid,
    $revision) {

    $owner_phids = PhabricatorOwnersOwner::loadAffiliatedUserPHIDs(
      array(
        $package->getID(),
      ));
    $owner_phids = array_fuse($owner_phids);

    // For the purposes of deciding whether the owners were involved in the
    // revision or not, consider a review by the package itself to count as
    // involvement. This can happen when human reviewers force-accept on
    // behalf of packages they don't own but have authority over.
    $owner_phids[$package->getPHID()] = $package->getPHID();

    // If the commit author is identifiable and a package owner, they're
    // involved.
    if ($author_phid) {
      if (isset($owner_phids[$author_phid])) {
        return true;
      }
    }

    // Otherwise, we need to find an owner as a reviewer.

    // If we don't have a revision, this is hopeless: no owners are involved.
    if (!$revision) {
      return true;
    }

    $accepted_statuses = array(
      DifferentialReviewerStatus::STATUS_ACCEPTED,
      DifferentialReviewerStatus::STATUS_ACCEPTED_OLDER,
    );
    $accepted_statuses = array_fuse($accepted_statuses);

    $found_accept = false;
    foreach ($revision->getReviewers() as $reviewer) {
      $reviewer_phid = $reviewer->getReviewerPHID();

      // If this reviewer isn't a package owner or the package itself,
      // just ignore them.
      if (empty($owner_phids[$reviewer_phid])) {
        continue;
      }

      // If this reviewer accepted the revision and owns the package (or is
      // the package), we've found an involved owner.
      if (isset($accepted_statuses[$reviewer->getReviewerStatus()])) {
        $found_accept = true;
        break;
      }
    }

    if ($found_accept) {
      return true;
    }

    return false;
  }

}
