<?php

/**
 * @task command Processing Commands
 * @task activate Activating Resources
 * @task release Releasing Resources
 * @task destroy Destroying Resources
 */
final class DrydockResourceUpdateWorker extends DrydockWorker {

  protected function doWork() {
    $resource_phid = $this->getTaskDataValue('resourcePHID');

    $hash = PhabricatorHash::digestForIndex($resource_phid);
    $lock_key = 'drydock.resource:'.$hash;

    $lock = PhabricatorGlobalLock::newLock($lock_key)
      ->lock(1);

    try {
      $resource = $this->loadResource($resource_phid);
      $this->updateResource($resource);
    } catch (Exception $ex) {
      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();
  }

  private function updateResource(DrydockResource $resource) {
    $this->processResourceCommands($resource);

    $resource_status = $resource->getStatus();
    switch ($resource_status) {
      case DrydockResourceStatus::STATUS_PENDING:
        $this->activateResource($resource);
        break;
      case DrydockResourceStatus::STATUS_ACTIVE:
        // Nothing to do.
        break;
      case DrydockResourceStatus::STATUS_RELEASED:
      case DrydockResourceStatus::STATUS_BROKEN:
        $this->destroyResource($resource);
        break;
      case DrydockResourceStatus::STATUS_DESTROYED:
        // Nothing to do.
        break;
    }

    $this->yieldIfExpiringResource($resource);
  }


/* -(  Processing Commands  )------------------------------------------------ */


  /**
   * @task command
   */
  private function processResourceCommands(DrydockResource $resource) {
    if (!$resource->canReceiveCommands()) {
      return;
    }

    $this->checkResourceExpiration($resource);

    $commands = $this->loadCommands($resource->getPHID());
    foreach ($commands as $command) {
      if (!$resource->canReceiveCommands()) {
        break;
      }

      $this->processResourceCommand($resource, $command);

      $command
        ->setIsConsumed(true)
        ->save();
    }
  }


  /**
   * @task command
   */
  private function processResourceCommand(
    DrydockResource $resource,
    DrydockCommand $command) {

    switch ($command->getCommand()) {
      case DrydockCommand::COMMAND_RELEASE:
        $this->releaseResource($resource);
        break;
    }
  }


/* -(  Activating Resources  )----------------------------------------------- */


  /**
   * @task activate
   */
  private function activateResource(DrydockResource $resource) {
    $blueprint = $resource->getBlueprint();
    $blueprint->activateResource($resource);
    $this->validateActivatedResource($blueprint, $resource);
  }


  /**
   * @task activate
   */
  private function validateActivatedResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource) {

    if (!$resource->isActivatedResource()) {
      throw new Exception(
        pht(
          'Blueprint "%s" (of type "%s") is not properly implemented: %s '.
          'must actually allocate the resource it returns.',
          $blueprint->getBlueprintName(),
          $blueprint->getClassName(),
          'allocateResource()'));
    }

  }


/* -(  Releasing Resources  )------------------------------------------------ */


  /**
   * @task release
   */
  private function releaseResource(DrydockResource $resource) {
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

    $this->destroyResource($resource);
  }


/* -(  Destroying Resources  )----------------------------------------------- */


  /**
   * @task destroy
   */
  private function destroyResource(DrydockResource $resource) {
    $blueprint = $resource->getBlueprint();
    $blueprint->destroyResource($resource);

    $resource
      ->setStatus(DrydockResourceStatus::STATUS_DESTROYED)
      ->save();
  }
}
