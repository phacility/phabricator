<?php

/**
 * @task update Updating Leases
 * @task command Processing Commands
 * @task allocator Drydock Allocator
 * @task acquire Acquiring Leases
 * @task activate Activating Leases
 * @task release Releasing Leases
 * @task break Breaking Leases
 * @task destroy Destroying Leases
 */
final class DrydockLeaseUpdateWorker extends DrydockWorker {

  protected function doWork() {
    $lease_phid = $this->getTaskDataValue('leasePHID');

    $hash = PhabricatorHash::digestForIndex($lease_phid);
    $lock_key = 'drydock.lease:'.$hash;

    $lock = PhabricatorGlobalLock::newLock($lock_key)
      ->lock(1);

    try {
      $lease = $this->loadLease($lease_phid);
      $this->handleUpdate($lease);
    } catch (Exception $ex) {
      $lock->unlock();
      $this->flushDrydockTaskQueue();
      throw $ex;
    }

    $lock->unlock();
  }


/* -(  Updating Leases  )---------------------------------------------------- */


  /**
   * @task update
   */
  private function handleUpdate(DrydockLease $lease) {
    try {
      $this->updateLease($lease);
    } catch (Exception $ex) {
      if ($this->isTemporaryException($ex)) {
        $this->yieldLease($lease, $ex);
      } else {
        $this->breakLease($lease, $ex);
      }
    }
  }


  /**
   * @task update
   */
  private function updateLease(DrydockLease $lease) {
    $this->processLeaseCommands($lease);

    $lease_status = $lease->getStatus();
    switch ($lease_status) {
      case DrydockLeaseStatus::STATUS_PENDING:
        $this->executeAllocator($lease);
        break;
      case DrydockLeaseStatus::STATUS_ACQUIRED:
        $this->activateLease($lease);
        break;
      case DrydockLeaseStatus::STATUS_ACTIVE:
        // Nothing to do.
        break;
      case DrydockLeaseStatus::STATUS_RELEASED:
      case DrydockLeaseStatus::STATUS_BROKEN:
        $this->destroyLease($lease);
        break;
      case DrydockLeaseStatus::STATUS_DESTROYED:
        break;
    }

    $this->yieldIfExpiringLease($lease);
  }


  /**
   * @task update
   */
  private function yieldLease(DrydockLease $lease, Exception $ex) {
    $duration = $this->getYieldDurationFromException($ex);

    $lease->logEvent(
      DrydockLeaseActivationYieldLogType::LOGCONST,
      array(
        'duration' => $duration,
      ));

    throw new PhabricatorWorkerYieldException($duration);
  }


/* -(  Processing Commands  )------------------------------------------------ */


  /**
   * @task command
   */
  private function processLeaseCommands(DrydockLease $lease) {
    if (!$lease->canReceiveCommands()) {
      return;
    }

    $this->checkLeaseExpiration($lease);

    $commands = $this->loadCommands($lease->getPHID());
    foreach ($commands as $command) {
      if (!$lease->canReceiveCommands()) {
        break;
      }

      $this->processLeaseCommand($lease, $command);

      $command
        ->setIsConsumed(true)
        ->save();
    }
  }


  /**
   * @task command
   */
  private function processLeaseCommand(
    DrydockLease $lease,
    DrydockCommand $command) {
    switch ($command->getCommand()) {
      case DrydockCommand::COMMAND_RELEASE:
        $this->releaseLease($lease);
        break;
    }
  }


/* -(  Drydock Allocator  )-------------------------------------------------- */


  /**
   * Find or build a resource which can satisfy a given lease request, then
   * acquire the lease.
   *
   * @param DrydockLease Requested lease.
   * @return void
   * @task allocator
   */
  private function executeAllocator(DrydockLease $lease) {
    $blueprints = $this->loadBlueprintsForAllocatingLease($lease);

    // If we get nothing back, that means no blueprint is defined which can
    // ever build the requested resource. This is a permanent failure, since
    // we don't expect to succeed no matter how many times we try.
    if (!$blueprints) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'No active Drydock blueprint exists which can ever allocate a '.
          'resource for lease "%s".',
          $lease->getPHID()));
    }

    // First, try to find a suitable open resource which we can acquire a new
    // lease on.
    $resources = $this->loadResourcesForAllocatingLease($blueprints, $lease);

    // If no resources exist yet, see if we can build one.
    if (!$resources) {
      $usable_blueprints = $this->removeOverallocatedBlueprints(
        $blueprints,
        $lease);

      // If we get nothing back here, some blueprint claims it can eventually
      // satisfy the lease, just not right now. This is a temporary failure,
      // and we expect allocation to succeed eventually.
      if (!$usable_blueprints) {
        $blueprints = $this->rankBlueprints($blueprints, $lease);

        // Try to actively reclaim unused resources. If we succeed, jump back
        // into the queue in an effort to claim it.
        foreach ($blueprints as $blueprint) {
          $reclaimed = $this->reclaimResources($blueprint, $lease);
          if ($reclaimed) {
            $lease->logEvent(
              DrydockLeaseReclaimLogType::LOGCONST,
              array(
                'resourcePHIDs' => array($reclaimed->getPHID()),
              ));

            throw new PhabricatorWorkerYieldException(15);
          }
        }

        $lease->logEvent(
          DrydockLeaseWaitingForResourcesLogType::LOGCONST,
          array(
            'blueprintPHIDs' => mpull($blueprints, 'getPHID'),
          ));

        throw new PhabricatorWorkerYieldException(15);
      }

      $usable_blueprints = $this->rankBlueprints($usable_blueprints, $lease);

      $exceptions = array();
      foreach ($usable_blueprints as $blueprint) {
        try {
          $resources[] = $this->allocateResource($blueprint, $lease);

          // Bail after allocating one resource, we don't need any more than
          // this.
          break;
        } catch (Exception $ex) {
          $exceptions[] = $ex;
        }
      }

      if (!$resources) {
        throw new PhutilAggregateException(
          pht(
            'All blueprints failed to allocate a suitable new resource when '.
            'trying to allocate lease "%s".',
            $lease->getPHID()),
          $exceptions);
      }

      $resources = $this->removeUnacquirableResources($resources, $lease);
      if (!$resources) {
        // If we make it here, we just built a resource but aren't allowed
        // to acquire it. We expect this during routine operation if the
        // resource prevents acquisition until it activates. Yield and wait
        // for activation.
        throw new PhabricatorWorkerYieldException(15);
      }

      // NOTE: We have not acquired the lease yet, so it is possible that the
      // resource we just built will be snatched up by some other lease before
      // we can acquire it. This is not problematic: we'll retry a little later
      // and should succeed eventually.
    }

    $resources = $this->rankResources($resources, $lease);

    $exceptions = array();
    $allocated = false;
    foreach ($resources as $resource) {
      try {
        $this->acquireLease($resource, $lease);
        $allocated = true;
        break;
      } catch (Exception $ex) {
        $exceptions[] = $ex;
      }
    }

    if (!$allocated) {
      throw new PhutilAggregateException(
        pht(
          'Unable to acquire lease "%s" on any resource.',
          $lease->getPHID()),
        $exceptions);
    }
  }


  /**
   * Get all the @{class:DrydockBlueprintImplementation}s which can possibly
   * build a resource to satisfy a lease.
   *
   * This method returns blueprints which might, at some time, be able to
   * build a resource which can satisfy the lease. They may not be able to
   * build that resource right now.
   *
   * @param DrydockLease Requested lease.
   * @return list<DrydockBlueprintImplementation> List of qualifying blueprint
   *   implementations.
   * @task allocator
   */
  private function loadBlueprintImplementationsForAllocatingLease(
    DrydockLease $lease) {

    $impls = DrydockBlueprintImplementation::getAllBlueprintImplementations();

    $keep = array();
    foreach ($impls as $key => $impl) {
      // Don't use disabled blueprint types.
      if (!$impl->isEnabled()) {
        continue;
      }

      // Don't use blueprint types which can't allocate the correct kind of
      // resource.
      if ($impl->getType() != $lease->getResourceType()) {
        continue;
      }

      if (!$impl->canAnyBlueprintEverAllocateResourceForLease($lease)) {
        continue;
      }

      $keep[$key] = $impl;
    }

    return $keep;
  }


  /**
   * Get all the concrete @{class:DrydockBlueprint}s which can possibly
   * build a resource to satisfy a lease.
   *
   * @param DrydockLease Requested lease.
   * @return list<DrydockBlueprint> List of qualifying blueprints.
   * @task allocator
   */
  private function loadBlueprintsForAllocatingLease(
    DrydockLease $lease) {
    $viewer = $this->getViewer();

    $impls = $this->loadBlueprintImplementationsForAllocatingLease($lease);
    if (!$impls) {
      return array();
    }

    $blueprint_phids = $lease->getAllowedBlueprintPHIDs();
    if (!$blueprint_phids) {
      $lease->logEvent(DrydockLeaseNoBlueprintsLogType::LOGCONST);
      return array();
    }

    $query = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withPHIDs($blueprint_phids)
      ->withBlueprintClasses(array_keys($impls))
      ->withDisabled(false);

    // The Drydock application itself is allowed to authorize anything. This
    // is primarily used for leases generated by CLI administrative tools.
    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    $authorizing_phid = $lease->getAuthorizingPHID();
    if ($authorizing_phid != $drydock_phid) {
      $blueprints = id(clone $query)
        ->withAuthorizedPHIDs(array($authorizing_phid))
        ->execute();
      if (!$blueprints) {
        // If we didn't hit any blueprints, check if this is an authorization
        // problem: re-execute the query without the authorization constraint.
        // If the second query hits blueprints, the overall configuration is
        // fine but this is an authorization problem. If the second query also
        // comes up blank, this is some other kind of configuration issue so
        // we fall through to the default pathway.
        $all_blueprints = $query->execute();
        if ($all_blueprints) {
          $lease->logEvent(
            DrydockLeaseNoAuthorizationsLogType::LOGCONST,
            array(
              'authorizingPHID' => $authorizing_phid,
            ));
          return array();
        }
      }
    } else {
      $blueprints = $query->execute();
    }

    $keep = array();
    foreach ($blueprints as $key => $blueprint) {
      if (!$blueprint->canEverAllocateResourceForLease($lease)) {
        continue;
      }

      $keep[$key] = $blueprint;
    }

    return $keep;
  }


  /**
   * Load a list of all resources which a given lease can possibly be
   * allocated against.
   *
   * @param list<DrydockBlueprint> Blueprints which may produce suitable
   *   resources.
   * @param DrydockLease Requested lease.
   * @return list<DrydockResource> Resources which may be able to allocate
   *   the lease.
   * @task allocator
   */
  private function loadResourcesForAllocatingLease(
    array $blueprints,
    DrydockLease $lease) {
    assert_instances_of($blueprints, 'DrydockBlueprint');
    $viewer = $this->getViewer();

    $resources = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withBlueprintPHIDs(mpull($blueprints, 'getPHID'))
      ->withTypes(array($lease->getResourceType()))
      ->withStatuses(
        array(
          DrydockResourceStatus::STATUS_PENDING,
          DrydockResourceStatus::STATUS_ACTIVE,
        ))
      ->execute();

    return $this->removeUnacquirableResources($resources, $lease);
  }


  /**
   * Remove resources which can not be acquired by a given lease from a list.
   *
   * @param list<DrydockResource> Candidate resources.
   * @param DrydockLease Acquiring lease.
   * @return list<DrydockResource> Resources which the lease may be able to
   *   acquire.
   * @task allocator
   */
  private function removeUnacquirableResources(
    array $resources,
    DrydockLease $lease) {
    $keep = array();
    foreach ($resources as $key => $resource) {
      $blueprint = $resource->getBlueprint();

      if (!$blueprint->canAcquireLeaseOnResource($resource, $lease)) {
        continue;
      }

      $keep[$key] = $resource;
    }

    return $keep;
  }


  /**
   * Remove blueprints which are too heavily allocated to build a resource for
   * a lease from a list of blueprints.
   *
   * @param list<DrydockBlueprint> List of blueprints.
   * @return list<DrydockBlueprint> List with blueprints that can not allocate
   *   a resource for the lease right now removed.
   * @task allocator
   */
  private function removeOverallocatedBlueprints(
    array $blueprints,
    DrydockLease $lease) {
    assert_instances_of($blueprints, 'DrydockBlueprint');

    $keep = array();

    foreach ($blueprints as $key => $blueprint) {
      if (!$blueprint->canAllocateResourceForLease($lease)) {
        continue;
      }

      $keep[$key] = $blueprint;
    }

    return $keep;
  }


  /**
   * Rank blueprints by suitability for building a new resource for a
   * particular lease.
   *
   * @param list<DrydockBlueprint> List of blueprints.
   * @param DrydockLease Requested lease.
   * @return list<DrydockBlueprint> Ranked list of blueprints.
   * @task allocator
   */
  private function rankBlueprints(array $blueprints, DrydockLease $lease) {
    assert_instances_of($blueprints, 'DrydockBlueprint');

    // TODO: Implement improvements to this ranking algorithm if they become
    // available.
    shuffle($blueprints);

    return $blueprints;
  }


  /**
   * Rank resources by suitability for allocating a particular lease.
   *
   * @param list<DrydockResource> List of resources.
   * @param DrydockLease Requested lease.
   * @return list<DrydockResource> Ranked list of resources.
   * @task allocator
   */
  private function rankResources(array $resources, DrydockLease $lease) {
    assert_instances_of($resources, 'DrydockResource');

    // TODO: Implement improvements to this ranking algorithm if they become
    // available.
    shuffle($resources);

    return $resources;
  }


  /**
   * Perform an actual resource allocation with a particular blueprint.
   *
   * @param DrydockBlueprint The blueprint to allocate a resource from.
   * @param DrydockLease Requested lease.
   * @return DrydockResource Allocated resource.
   * @task allocator
   */
  private function allocateResource(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {
    $resource = $blueprint->allocateResource($lease);
    $this->validateAllocatedResource($blueprint, $resource, $lease);

    // If this resource was allocated as a pending resource, queue a task to
    // activate it.
    if ($resource->getStatus() == DrydockResourceStatus::STATUS_PENDING) {
      PhabricatorWorker::scheduleTask(
        'DrydockResourceUpdateWorker',
        array(
          'resourcePHID' => $resource->getPHID(),
        ),
        array(
          'objectPHID' => $resource->getPHID(),
        ));
    }

    return $resource;
  }


  /**
   * Check that the resource a blueprint allocated is roughly the sort of
   * object we expect.
   *
   * @param DrydockBlueprint Blueprint which built the resource.
   * @param wild Thing which the blueprint claims is a valid resource.
   * @param DrydockLease Lease the resource was allocated for.
   * @return void
   * @task allocator
   */
  private function validateAllocatedResource(
    DrydockBlueprint $blueprint,
    $resource,
    DrydockLease $lease) {

    if (!($resource instanceof DrydockResource)) {
      throw new Exception(
        pht(
          'Blueprint "%s" (of type "%s") is not properly implemented: %s must '.
          'return an object of type %s or throw, but returned something else.',
          $blueprint->getBlueprintName(),
          $blueprint->getClassName(),
          'allocateResource()',
          'DrydockResource'));
    }

    if (!$resource->isAllocatedResource()) {
      throw new Exception(
        pht(
          'Blueprint "%s" (of type "%s") is not properly implemented: %s '.
          'must actually allocate the resource it returns.',
          $blueprint->getBlueprintName(),
          $blueprint->getClassName(),
          'allocateResource()'));
    }

    $resource_type = $resource->getType();
    $lease_type = $lease->getResourceType();

    if ($resource_type !== $lease_type) {
      throw new Exception(
        pht(
          'Blueprint "%s" (of type "%s") is not properly implemented: it '.
          'built a resource of type "%s" to satisfy a lease requesting a '.
          'resource of type "%s".',
          $blueprint->getBlueprintName(),
          $blueprint->getClassName(),
          $resource_type,
          $lease_type));
    }
  }

  private function reclaimResources(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {
    $viewer = $this->getViewer();

    $resources = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withBlueprintPHIDs(array($blueprint->getPHID()))
      ->withStatuses(
        array(
          DrydockResourceStatus::STATUS_ACTIVE,
        ))
      ->execute();

    // TODO: We could be much smarter about this and try to release long-unused
    // resources, resources with many similar copies, old resources, resources
    // that are cheap to rebuild, etc.
    shuffle($resources);

    foreach ($resources as $resource) {
      if ($this->canReclaimResource($resource)) {
        $this->reclaimResource($resource, $lease);
        return $resource;
      }
    }

    return null;
  }


/* -(  Acquiring Leases  )--------------------------------------------------- */


  /**
   * Perform an actual lease acquisition on a particular resource.
   *
   * @param DrydockResource Resource to acquire a lease on.
   * @param DrydockLease Lease to acquire.
   * @return void
   * @task acquire
   */
  private function acquireLease(
    DrydockResource $resource,
    DrydockLease $lease) {

    $blueprint = $resource->getBlueprint();
    $blueprint->acquireLease($resource, $lease);

    $this->validateAcquiredLease($blueprint, $resource, $lease);

    // If this lease has been acquired but not activated, queue a task to
    // activate it.
    if ($lease->getStatus() == DrydockLeaseStatus::STATUS_ACQUIRED) {
      $this->queueTask(
        __CLASS__,
        array(
          'leasePHID' => $lease->getPHID(),
        ),
        array(
          'objectPHID' => $lease->getPHID(),
        ));
    }
  }


  /**
   * Make sure that a lease was really acquired properly.
   *
   * @param DrydockBlueprint Blueprint which created the resource.
   * @param DrydockResource Resource which was acquired.
   * @param DrydockLease The lease which was supposedly acquired.
   * @return void
   * @task acquire
   */
  private function validateAcquiredLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {

    if (!$lease->isAcquiredLease()) {
      throw new Exception(
        pht(
          'Blueprint "%s" (of type "%s") is not properly implemented: it '.
          'returned from "%s" without acquiring a lease.',
          $blueprint->getBlueprintName(),
          $blueprint->getClassName(),
          'acquireLease()'));
    }

    $lease_phid = $lease->getResourcePHID();
    $resource_phid = $resource->getPHID();

    if ($lease_phid !== $resource_phid) {
      throw new Exception(
        pht(
          'Blueprint "%s" (of type "%s") is not properly implemented: it '.
          'returned from "%s" with a lease acquired on the wrong resource.',
          $blueprint->getBlueprintName(),
          $blueprint->getClassName(),
          'acquireLease()'));
    }
  }


/* -(  Activating Leases  )-------------------------------------------------- */


  /**
   * @task activate
   */
  private function activateLease(DrydockLease $lease) {
    $resource = $lease->getResource();
    if (!$resource) {
      throw new Exception(
        pht('Trying to activate lease with no resource.'));
    }

    $resource_status = $resource->getStatus();

    if ($resource_status == DrydockResourceStatus::STATUS_PENDING) {
      throw new PhabricatorWorkerYieldException(15);
    }

    if ($resource_status != DrydockResourceStatus::STATUS_ACTIVE) {
      throw new Exception(
        pht(
          'Trying to activate lease on a dead resource (in status "%s").',
          $resource_status));
    }

    // NOTE: We can race resource destruction here. Between the time we
    // performed the read above and now, the resource might have closed, so
    // we may activate leases on dead resources. At least for now, this seems
    // fine: a resource dying right before we activate a lease on it should not
    // be distinguishable from a resource dying right after we activate a lease
    // on it. We end up with an active lease on a dead resource either way, and
    // can not prevent resources dying from lightning strikes.

    $blueprint = $resource->getBlueprint();
    $blueprint->activateLease($resource, $lease);
    $this->validateActivatedLease($blueprint, $resource, $lease);
  }

  /**
   * @task activate
   */
  private function validateActivatedLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {

    if (!$lease->isActivatedLease()) {
      throw new Exception(
        pht(
          'Blueprint "%s" (of type "%s") is not properly implemented: it '.
          'returned from "%s" without activating a lease.',
          $blueprint->getBlueprintName(),
          $blueprint->getClassName(),
          'acquireLease()'));
    }

  }


/* -(  Releasing Leases  )--------------------------------------------------- */


  /**
   * @task release
   */
  private function releaseLease(DrydockLease $lease) {
    $lease
      ->setStatus(DrydockLeaseStatus::STATUS_RELEASED)
      ->save();

    $lease->logEvent(DrydockLeaseReleasedLogType::LOGCONST);

    $resource = $lease->getResource();
    if ($resource) {
      $blueprint = $resource->getBlueprint();
      $blueprint->didReleaseLease($resource, $lease);
    }

    $this->destroyLease($lease);
  }


/* -(  Breaking Leases  )---------------------------------------------------- */


  /**
   * @task break
   */
  protected function breakLease(DrydockLease $lease, Exception $ex) {
    switch ($lease->getStatus()) {
      case DrydockLeaseStatus::STATUS_BROKEN:
      case DrydockLeaseStatus::STATUS_RELEASED:
      case DrydockLeaseStatus::STATUS_DESTROYED:
        throw new PhutilProxyException(
          pht(
            'Unexpected failure while destroying lease ("%s").',
            $lease->getPHID()),
          $ex);
    }

    $lease
      ->setStatus(DrydockLeaseStatus::STATUS_BROKEN)
      ->save();

    $lease->logEvent(
      DrydockLeaseActivationFailureLogType::LOGCONST,
      array(
        'class' => get_class($ex),
        'message' => $ex->getMessage(),
      ));

    $lease->awakenTasks();

    $this->queueTask(
      __CLASS__,
      array(
        'leasePHID' => $lease->getPHID(),
      ),
      array(
        'objectPHID' => $lease->getPHID(),
      ));

    throw new PhabricatorWorkerPermanentFailureException(
      pht(
        'Permanent failure while activating lease ("%s"): %s',
        $lease->getPHID(),
        $ex->getMessage()));
  }


/* -(  Destroying Leases  )-------------------------------------------------- */


  /**
   * @task destroy
   */
  private function destroyLease(DrydockLease $lease) {
    $resource = $lease->getResource();

    if ($resource) {
      $blueprint = $resource->getBlueprint();
      $blueprint->destroyLease($resource, $lease);
    }

    DrydockSlotLock::releaseLocks($lease->getPHID());

    $lease
      ->setStatus(DrydockLeaseStatus::STATUS_DESTROYED)
      ->save();

    $lease->logEvent(DrydockLeaseDestroyedLogType::LOGCONST);

    $lease->awakenTasks();
  }

}
