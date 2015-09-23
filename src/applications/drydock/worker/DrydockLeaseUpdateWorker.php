<?php

final class DrydockLeaseUpdateWorker extends DrydockWorker {

  protected function doWork() {
    $lease_phid = $this->getTaskDataValue('leasePHID');

    $hash = PhabricatorHash::digestForIndex($lease_phid);
    $lock_key = 'drydock.lease:'.$hash;

    $lock = PhabricatorGlobalLock::newLock($lock_key)
      ->lock(1);

    try {
      $lease = $this->loadLease($lease_phid);
      $this->updateLease($lease);
    } catch (Exception $ex) {
      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();
  }

  private function updateLease(DrydockLease $lease) {
    if ($lease->getStatus() != DrydockLeaseStatus::STATUS_ACTIVE) {
      return;
    }

    $viewer = $this->getViewer();
    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    // Check if the lease has expired. If it is, we're going to send it a
    // release command. This command will be handled immediately below, it
    // just generates a command log and improves consistency.
    $now = PhabricatorTime::getNow();
    $expires = $lease->getUntil();
    if ($expires && ($expires <= $now)) {
      $command = DrydockCommand::initializeNewCommand($viewer)
        ->setTargetPHID($lease->getPHID())
        ->setAuthorPHID($drydock_phid)
        ->setCommand(DrydockCommand::COMMAND_RELEASE)
        ->save();
    }

    $commands = $this->loadCommands($lease->getPHID());
    foreach ($commands as $command) {
      if ($lease->getStatus() != DrydockLeaseStatus::STATUS_ACTIVE) {
        // Leases can't receive commands before they activate or after they
        // release.
        break;
      }

      $this->processCommand($lease, $command);

      $command
        ->setIsConsumed(true)
        ->save();
    }

    // If this is the task which will eventually release the lease after it
    // expires but it is still active, reschedule the task to run after the
    // lease expires. This can happen if the lease's expiration was pushed
    // forward.
    if ($lease->getStatus() == DrydockLeaseStatus::STATUS_ACTIVE) {
      if ($this->getTaskDataValue('isExpireTask') && $expires) {
        throw new PhabricatorWorkerYieldException($expires - $now);
      }
    }
  }

  private function processCommand(
    DrydockLease $lease,
    DrydockCommand $command) {
    switch ($command->getCommand()) {
      case DrydockCommand::COMMAND_RELEASE:
        $this->releaseLease($lease);
        break;
    }
  }

  private function releaseLease(DrydockLease $lease) {
    $lease->openTransaction();
      $lease
        ->setStatus(DrydockLeaseStatus::STATUS_RELEASED)
        ->save();

      // TODO: Hold slot locks until destruction?
      DrydockSlotLock::releaseLocks($lease->getPHID());
    $lease->saveTransaction();

    PhabricatorWorker::scheduleTask(
      'DrydockLeaseDestroyWorker',
      array(
        'leasePHID' => $lease->getPHID(),
      ),
      array(
        'objectPHID' => $lease->getPHID(),
      ));

    $resource = $lease->getResource();
    $blueprint = $resource->getBlueprint();

    $blueprint->didReleaseLease($resource, $lease);
  }

}
