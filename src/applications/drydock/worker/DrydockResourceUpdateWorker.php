<?php

final class DrydockResourceUpdateWorker extends DrydockWorker {

  protected function doWork() {
    $resource_phid = $this->getTaskDataValue('resourcePHID');

    $hash = PhabricatorHash::digestForIndex($resource_phid);
    $lock_key = 'drydock.resource:'.$hash;

    $lock = PhabricatorGlobalLock::newLock($lock_key)
      ->lock(1);

    $resource = $this->loadResource($resource_phid);
    $this->updateResource($resource);

    $lock->unlock();
  }

  private function updateResource(DrydockResource $resource) {
    $commands = $this->loadCommands($resource->getPHID());
    foreach ($commands as $command) {
      if ($resource->getStatus() != DrydockResourceStatus::STATUS_ACTIVE) {
        // Resources can't receive commands before they activate or after they
        // release.
        break;
      }

      $this->processCommand($resource, $command);

      $command
        ->setIsConsumed(true)
        ->save();
    }
  }

  private function processCommand(
    DrydockResource $resource,
    DrydockCommand $command) {

    switch ($command->getCommand()) {
      case DrydockCommand::COMMAND_RELEASE:
        $this->releaseResource($resource);
        break;
    }
  }

  private function releaseResource(DrydockResource $resource) {
    if ($resource->getStatus() != DrydockResourceStatus::STATUS_ACTIVE) {
      // If we had multiple release commands
      // This command is only meaningful to resources in the "Open" state.
      return;
    }

    $viewer = $this->getViewer();
    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    $resource->openTransaction();
      $resource
        ->setStatus(DrydockResourceStatus::STATUS_RELEASED)
        ->save();

      // TODO: Hold slot locks until destruction?
      DrydockSlotLock::releaseLocks($resource->getPHID());
    $resource->saveTransaction();

    $statuses = array(
      DrydockLeaseStatus::STATUS_PENDING,
      DrydockLeaseStatus::STATUS_ACQUIRED,
      DrydockLeaseStatus::STATUS_ACTIVE,
    );

    $leases = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withResourcePHIDs(array($resource->getPHID()))
      ->withStatuses($statuses)
      ->execute();

    foreach ($leases as $lease) {
      $command = DrydockCommand::initializeNewCommand($viewer)
        ->setTargetPHID($lease->getPHID())
        ->setAuthorPHID($drydock_phid)
        ->setCommand(DrydockCommand::COMMAND_RELEASE)
        ->save();

      $lease->scheduleUpdate();
    }

    PhabricatorWorker::scheduleTask(
      'DrydockResourceDestroyWorker',
      array(
        'resourcePHID' => $resource->getPHID(),
      ),
      array(
        'objectPHID' => $resource->getPHID(),
      ));
  }

}
