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

}
