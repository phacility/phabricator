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

    if ($affected_packages) {
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

        if ($package->getAuditingEnabled()) {
          $reasons = $this->checkAuditReasons($commit, $package);
          if ($reasons) {
            $audit_status =
              PhabricatorAuditStatusConstants::AUDIT_REQUIRED;
          } else {
            $audit_status =
              PhabricatorAuditStatusConstants::AUDIT_NOT_REQUIRED;
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
  }

  private function checkAuditReasons(
    PhabricatorRepositoryCommit $commit,
    PhabricatorOwnersPackage $package) {

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());

    $reasons = array();

    if ($data->getCommitDetail('vsDiff')) {
      $reasons[] = 'Changed After Revision Was Accepted';
    }

    $commit_author_phid = $data->getCommitDetail('authorPHID');
    if (!$commit_author_phid) {
      $reasons[] = 'Commit Author Not Recognized';
    }

    $revision_id = $data->getCommitDetail('differential.revisionID');

    $revision_author_phid = null;
    $commit_reviewedby_phid = null;

    if ($revision_id) {
      $revision = id(new DifferentialRevisionQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withIDs(array($revision_id))
        ->executeOne();
      if ($revision) {
        $revision_author_phid = $revision->getAuthorPHID();
        $commit_reviewedby_phid = $data->getCommitDetail('reviewerPHID');
        if ($revision_author_phid !== $commit_author_phid) {
          $reasons[] = 'Author Not Matching with Revision';
        }
      } else {
        $reasons[] = 'Revision Not Found';
      }

    } else {
      $reasons[] = 'No Revision Specified';
    }

    $owners_phids = PhabricatorOwnersOwner::loadAffiliatedUserPHIDs(
      array($package->getID()));

    if (!($commit_author_phid && in_array($commit_author_phid, $owners_phids) ||
        $commit_reviewedby_phid && in_array($commit_reviewedby_phid,
          $owners_phids))) {
      $reasons[] = 'Owners Not Involved';
    }

    return $reasons;
  }

}
