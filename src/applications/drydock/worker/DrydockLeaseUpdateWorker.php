<?php

final class DrydockLeaseUpdateWorker extends DrydockWorker {

  protected function doWork() {
    $lease_phid = $this->getTaskDataValue('leasePHID');

    $hash = PhabricatorHash::digestForIndex($lease_phid);
    $lock_key = 'drydock.lease:'.$hash;

    $lock = PhabricatorGlobalLock::newLock($lock_key)
      ->lock(1);

    $lease = $this->loadLease($lease_phid);
    $this->updateLease($lease);

    $lock->unlock();
  }

  private function updateLease(DrydockLease $lease) {
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
