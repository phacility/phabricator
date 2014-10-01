<?php

final class DiffusionDoorkeeperCommitFeedStoryPublisher
  extends DoorkeeperFeedStoryPublisher {

  private $auditRequests;
  private $activePHIDs;
  private $passivePHIDs;

  private function getAuditRequests() {
    return $this->auditRequests;
  }

  public function canPublishStory(PhabricatorFeedStory $story, $object) {
    return
      ($story instanceof PhabricatorApplicationTransactionFeedStory) &&
      ($object instanceof PhabricatorRepositoryCommit);
  }

  public function isStoryAboutObjectCreation($object) {
    // TODO: Although creation stories exist, they currently don't have a
    // primary object PHID set, so they'll never make it here because they
    // won't pass `canPublishStory()`.
    return false;
  }

  public function isStoryAboutObjectClosure($object) {
    // TODO: This isn't quite accurate, but pretty close: check if this story
    // is a close (which clearly is about object closure) or is an "Accept" and
    // the commit is fully audited (which is almost certainly a closure).
    // After ApplicationTransactions, we could annotate feed stories more
    // explicitly.

    $fully_audited = PhabricatorAuditCommitStatusConstants::FULLY_AUDITED;

    $story = $this->getFeedStory();
    $xaction = $story->getPrimaryTransaction();
    switch ($xaction->getTransactionType()) {
      case PhabricatorAuditActionConstants::ACTION:
        switch ($xaction->getNewValue()) {
          case PhabricatorAuditActionConstants::CLOSE:
            return true;
          case PhabricatorAuditActionConstants::ACCEPT:
            if ($object->getAuditStatus() == $fully_audited) {
              return true;
            }
            break;
        }
    }

    return false;
  }

  public function willPublishStory($commit) {
    $requests = id(new DiffusionCommitQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($commit->getPHID()))
      ->needAuditRequests(true)
      ->executeOne()
      ->getAudits();

    // TODO: This is messy and should be generalized, but we don't have a good
    // query for it yet. Since we run in the daemons, just do the easiest thing
    // we can for the moment. Figure out who all of the "active" (need to
    // audit) and "passive" (no action necessary) users are.

    $auditor_phids = mpull($requests, 'getAuditorPHID');
    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($auditor_phids)
      ->execute();

    $active = array();
    $passive = array();

    foreach ($requests as $request) {
      $status = $request->getAuditStatus();

      $object = idx($objects, $request->getAuditorPHID());
      if (!$object) {
        continue;
      }

      $request_phids = array();
      if ($object instanceof PhabricatorUser) {
        $request_phids = array($object->getPHID());
      } else if ($object instanceof PhabricatorOwnersPackage) {
        $request_phids = PhabricatorOwnersOwner::loadAffiliatedUserPHIDs(
          array($object->getID()));
      } else if ($object instanceof PhabricatorProject) {
        $project = id(new PhabricatorProjectQuery())
          ->setViewer($this->getViewer())
          ->withIDs(array($object->getID()))
          ->needMembers(true)
          ->executeOne();
        $request_phids = $project->getMemberPHIDs();
      } else {
        // Dunno what this is.
        $request_phids = array();
      }

      switch ($status) {
        case PhabricatorAuditStatusConstants::AUDIT_REQUIRED:
        case PhabricatorAuditStatusConstants::AUDIT_REQUESTED:
        case PhabricatorAuditStatusConstants::CONCERNED:
          $active += array_fuse($request_phids);
          break;
        default:
          $passive += array_fuse($request_phids);
          break;
      }
    }


    // Remove "Active" users from the "Passive" list.
    $passive = array_diff_key($passive, $active);

    $this->activePHIDs = $active;
    $this->passivePHIDs = $passive;
    $this->auditRequests = $requests;

    return $commit;
  }

  public function getOwnerPHID($object) {
    return $object->getAuthorPHID();
  }

  public function getActiveUserPHIDs($object) {
    return $this->activePHIDs;
  }

  public function getPassiveUserPHIDs($object) {
    return $this->passivePHIDs;
  }

  public function getCCUserPHIDs($object) {
    return PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $object->getPHID());
  }

  public function getObjectTitle($object) {
    $prefix = $this->getTitlePrefix($object);

    $repository = $object->getRepository();
    $name = $repository->formatCommitName($object->getCommitIdentifier());

    $title = $object->getSummary();

    return ltrim("{$prefix} {$name}: {$title}");
  }

  public function getObjectURI($object) {
    $repository = $object->getRepository();
    $name = $repository->formatCommitName($object->getCommitIdentifier());
    return PhabricatorEnv::getProductionURI('/'.$name);
  }

  public function getObjectDescription($object) {
    $data = $object->loadCommitData();
    if ($data) {
      return $data->getCommitMessage();
    }
    return null;
  }

  public function isObjectClosed($object) {
    switch ($object->getAuditStatus()) {
      case PhabricatorAuditCommitStatusConstants::NEEDS_AUDIT:
      case PhabricatorAuditCommitStatusConstants::CONCERN_RAISED:
      case PhabricatorAuditCommitStatusConstants::PARTIALLY_AUDITED:
        return false;
      default:
        return true;
    }
  }

  public function getResponsibilityTitle($object) {
    $prefix = $this->getTitlePrefix($object);
    return pht('%s Audit', $prefix);
  }

  private function getTitlePrefix(PhabricatorRepositoryCommit $commit) {
    $prefix_key = 'metamta.diffusion.subject-prefix';
    return PhabricatorEnv::getEnvConfig($prefix_key);
  }

}
