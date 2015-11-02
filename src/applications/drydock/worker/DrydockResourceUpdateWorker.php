<?php

/**
 * @task update Updating Resources
 * @task command Processing Commands
 * @task activate Activating Resources
 * @task release Releasing Resources
 * @task break Breaking Resources
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
      $this->handleUpdate($resource);
    } catch (Exception $ex) {
      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();
  }


/* -(  Updating Resources  )------------------------------------------------- */


  /**
   * Update a resource, handling exceptions thrown during the update.
   *
   * @param DrydockReosource Resource to update.
   * @return void
   * @task update
   */
  private function handleUpdate(DrydockResource $resource) {
    try {
      $this->updateResource($resource);
    } catch (Exception $ex) {
      if ($this->isTemporaryException($ex)) {
        $this->yieldResource($resource, $ex);
      } else {
        $this->breakResource($resource, $ex);
      }
    }
  }


  /**
   * Update a resource.
   *
   * @param DrydockResource Resource to update.
   * @return void
   * @task update
   */
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


  /**
   * Convert a temporary exception into a yield.
   *
   * @param DrydockResource Resource to yield.
   * @param Exception Temporary exception worker encountered.
   * @task update
   */
  private function yieldResource(DrydockResource $resource, Exception $ex) {
    $duration = $this->getYieldDurationFromException($ex);

    $resource->logEvent(
      DrydockResourceActivationYieldLogType::LOGCONST,
      array(
        'duration' => $duration,
      ));

    throw new PhabricatorWorkerYieldException($duration);
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

    $resource
      ->setStatus(DrydockResourceStatus::STATUS_RELEASED)
      ->save();

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


/* -(  Breaking Resources  )------------------------------------------------- */


  /**
   * @task break
   */
  private function breakResource(DrydockResource $resource, Exception $ex) {
    switch ($resource->getStatus()) {
      case DrydockResourceStatus::STATUS_BROKEN:
      case DrydockResourceStatus::STATUS_RELEASED:
      case DrydockResourceStatus::STATUS_DESTROYED:
        // If the resource was already broken, just throw a normal exception.
        // This will retry the task eventually.
        throw new PhutilProxyException(
          pht(
            'Unexpected failure while destroying resource ("%s").',
            $resource->getPHID()),
          $ex);
    }

    $resource
      ->setStatus(DrydockResourceStatus::STATUS_BROKEN)
      ->save();

    $resource->scheduleUpdate();

    $resource->logEvent(
      DrydockResourceActivationFailureLogType::LOGCONST,
      array(
        'class' => get_class($ex),
        'message' => $ex->getMessage(),
      ));

    throw new PhabricatorWorkerPermanentFailureException(
      pht(
        'Permanent failure while activating resource ("%s"): %s',
        $resource->getPHID(),
        $ex->getMessage()));
  }


/* -(  Destroying Resources  )----------------------------------------------- */


  /**
   * @task destroy
   */
  private function destroyResource(DrydockResource $resource) {
    $blueprint = $resource->getBlueprint();
    $blueprint->destroyResource($resource);

    DrydockSlotLock::releaseLocks($resource->getPHID());

    $resource
      ->setStatus(DrydockResourceStatus::STATUS_DESTROYED)
      ->save();
  }
}
