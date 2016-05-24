<?php

final class PhabricatorRepositoryCommitOwnersWorker
  extends PhabricatorRepositoryCommitParserWorker {

  protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $this->triggerOwnerAudits($repository, $commit);

    $commit->writeImportStatusFlag(
      PhabricatorRepositoryCommit::IMPORTED_OWNERS);

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

    if (!$affected_packages) {
      return;
    }

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());
    $commit->attachCommitData($data);

    $author_phid = $data->getCommitDetail('authorPHID');
    $revision_id = $data->getCommitDetail('differential.revisionID');
    if ($revision_id) {
      $revision = id(new DifferentialRevisionQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withIDs(array($revision_id))
        ->needReviewerStatus(true)
        ->executeOne();
    } else {
      $revision = null;
    }

    $requests = id(new PhabricatorRepositoryAuditRequest())
      ->loadAllWhere(
        'commitPHID = %s',
        $commit->getPHID());
    $requests = mpull($requests, null, 'getAuditorPHID');


    foreach ($affected_packages as $package) {
      $request = idx($requests, $package->getPHID());
      if ($request) {
        // Don't update request if it exists already.
        continue;
      }

      if ($package->isArchived()) {
        // Don't trigger audits if the package is archived.
        continue;
      }

      if ($package->getAuditingEnabled()) {
        $reasons = $this->checkAuditReasons(
          $commit,
          $package,
          $author_phid,
          $revision);

        if ($reasons) {
          $audit_status = PhabricatorAuditStatusConstants::AUDIT_REQUIRED;
        } else {
          $audit_status = PhabricatorAuditStatusConstants::AUDIT_NOT_REQUIRED;
        }
      } else {
        $reasons = array();
        $audit_status = PhabricatorAuditStatusConstants::NONE;
      }

      $relationship = new PhabricatorRepositoryAuditRequest();
      $relationship->setAuditorPHID($package->getPHID());
      $relationship->setCommitPHID($commit->getPHID());
      $relationship->setAuditReasons($reasons);
      $relationship->setAuditStatus($audit_status);

      $relationship->save();

      $requests[$package->getPHID()] = $relationship;
    }

    $commit->updateAuditStatus($requests);
    $commit->save();
  }

  private function checkAuditReasons(
    PhabricatorRepositoryCommit $commit,
    PhabricatorOwnersPackage $package,
    $author_phid,
    $revision) {

    $owner_phids = PhabricatorOwnersOwner::loadAffiliatedUserPHIDs(
      array(
        $package->getID(),
      ));
    $owner_phids = array_fuse($owner_phids);

    $reasons = array();

    if (!$author_phid) {
      $reasons[] = pht('Commit Author Not Recognized');
    } else if (isset($owner_phids[$author_phid])) {
      return $reasons;
    }

    if (!$revision) {
      $reasons[] = pht('No Revision Specified');
      return $reasons;
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

    if (!$found_accept) {
      $reasons[] = pht('Owners Not Involved');
    }

    return $reasons;
  }

}
