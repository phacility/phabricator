<?php

abstract class DrydockWorker extends PhabricatorWorker {

  protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  protected function loadLease($lease_phid) {
    $viewer = $this->getViewer();

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($lease_phid))
      ->executeOne();
    if (!$lease) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No such lease "%s"!', $lease_phid));
    }

    return $lease;
  }

  protected function loadResource($resource_phid) {
    $viewer = $this->getViewer();

    $resource = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($resource_phid))
      ->executeOne();
    if (!$resource) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No such resource "%s"!', $resource_phid));
    }

    return $resource;
  }

  protected function loadOperation($operation_phid) {
    $viewer = $this->getViewer();

    $operation = id(new DrydockRepositoryOperationQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($operation_phid))
      ->executeOne();
    if (!$operation) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No such operation "%s"!', $operation_phid));
    }

    return $operation;
  }

  protected function loadCommands($target_phid) {
    $viewer = $this->getViewer();

    $commands = id(new DrydockCommandQuery())
      ->setViewer($viewer)
      ->withTargetPHIDs(array($target_phid))
      ->withConsumed(false)
      ->execute();

    $commands = msort($commands, 'getID');

    return $commands;
  }

  protected function checkLeaseExpiration(DrydockLease $lease) {
    $this->checkObjectExpiration($lease);
  }

  protected function checkResourceExpiration(DrydockResource $resource) {
    $this->checkObjectExpiration($resource);
  }

  private function checkObjectExpiration($object) {
    // Check if the resource or lease has expired. If it has, we're going to
    // send it a release command.

    // This command is sent from within the update worker so it is handled
    // immediately, but doing this generates a log and improves consistency.

    $expires = $object->getUntil();
    if (!$expires) {
      return;
    }

    $now = PhabricatorTime::getNow();
    if ($expires > $now) {
      return;
    }

    $viewer = $this->getViewer();
    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    $command = DrydockCommand::initializeNewCommand($viewer)
      ->setTargetPHID($object->getPHID())
      ->setAuthorPHID($drydock_phid)
      ->setCommand(DrydockCommand::COMMAND_RELEASE)
      ->save();
  }

  protected function yieldIfExpiringLease(DrydockLease $lease) {
    if (!$lease->canReceiveCommands()) {
      return;
    }

    $this->yieldIfExpiring($lease->getUntil());
  }

  protected function yieldIfExpiringResource(DrydockResource $resource) {
    if (!$resource->canReceiveCommands()) {
      return;
    }

    $this->yieldIfExpiring($resource->getUntil());
  }

  private function yieldIfExpiring($expires) {
    if (!$expires) {
      return;
    }

    if (!$this->getTaskDataValue('isExpireTask')) {
      return;
    }

    $now = PhabricatorTime::getNow();
    throw new PhabricatorWorkerYieldException($expires - $now);
  }

  protected function isTemporaryException(Exception $ex) {
    if ($ex instanceof PhabricatorWorkerYieldException) {
      return true;
    }

    if ($ex instanceof DrydockSlotLockException) {
      return true;
    }

    if ($ex instanceof PhutilAggregateException) {
      $any_temporary = false;
      foreach ($ex->getExceptions() as $sub) {
        if ($this->isTemporaryException($sub)) {
          $any_temporary = true;
          break;
        }
      }
      if ($any_temporary) {
        return true;
      }
    }

    if ($ex instanceof PhutilProxyException) {
      return $this->isTemporaryException($ex->getPreviousException());
    }

    return false;
  }

  protected function getYieldDurationFromException(Exception $ex) {
    if ($ex instanceof PhabricatorWorkerYieldException) {
      return $ex->getDuration();
    }

    if ($ex instanceof DrydockSlotLockException) {
      return 5;
    }

    return 15;
  }

  protected function flushDrydockTaskQueue() {
    // NOTE: By default, queued tasks are not scheduled if the current task
    // fails. This is a good, safe default behavior. For example, it can
    // protect us from executing side effect tasks too many times, like
    // sending extra email.

    // However, it is not the behavior we want in Drydock, because we queue
    // followup tasks after lease and resource failures and want them to
    // execute in order to clean things up.

    // At least for now, we just explicitly flush the queue before exiting
    // with a failure to make sure tasks get queued up properly.
    try {
      $this->flushTaskQueue();
    } catch (Exception $ex) {
      // If this fails, we want to swallow the exception so the caller throws
      // the original error, since we're more likely to be able to understand
      // and fix the problem if we have the original error than if we replace
      // it with this one.
      phlog($ex);
    }

    return $this;
  }

  protected function canReclaimResource(DrydockResource $resource) {
    $viewer = $this->getViewer();

    // Don't reclaim a resource if it has been updated recently. If two
    // leases are fighting, we don't want them to keep reclaiming resources
    // from one another forever without making progress, so make resources
    // immune to reclamation for a little while after they activate or update.

    // TODO: It would be nice to use a more narrow time here, like "last
    // activation or lease release", but we don't currently store that
    // anywhere.

    $updated = $resource->getDateModified();
    $now = PhabricatorTime::getNow();
    $ago = ($now - $updated);
    if ($ago < phutil_units('3 minutes in seconds')) {
      return false;
    }

    $statuses = array(
      DrydockLeaseStatus::STATUS_PENDING,
      DrydockLeaseStatus::STATUS_ACQUIRED,
      DrydockLeaseStatus::STATUS_ACTIVE,
      DrydockLeaseStatus::STATUS_RELEASED,
      DrydockLeaseStatus::STATUS_BROKEN,
    );

    // Don't reclaim resources that have any active leases.
    $leases = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withResourcePHIDs(array($resource->getPHID()))
      ->withStatuses($statuses)
      ->setLimit(1)
      ->execute();
    if ($leases) {
      return false;
    }

    return true;
  }

  protected function reclaimResource(
    DrydockResource $resource,
    DrydockLease $lease) {
    $viewer = $this->getViewer();

    $command = DrydockCommand::initializeNewCommand($viewer)
      ->setTargetPHID($resource->getPHID())
      ->setAuthorPHID($lease->getPHID())
      ->setCommand(DrydockCommand::COMMAND_RECLAIM)
      ->save();

    $resource->scheduleUpdate();

    return $this;
  }

}
