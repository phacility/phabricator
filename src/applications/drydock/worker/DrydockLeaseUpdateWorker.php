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
    } catch (DrydockAcquiredBrokenResourceException $ex) {
      // If this lease acquired a resource but failed to activate, we don't
      // need to break the lease. We can throw it back in the pool and let
      // it take another shot at acquiring a new resource.

      // Before we throw it back, release any locks the lease is holding.
      DrydockSlotLock::releaseLocks($lease->getPHID());

      $lease
        ->setStatus(DrydockLeaseStatus::STATUS_PENDING)
        ->setResourcePHID(null)
        ->save();

      $lease->logEvent(
        DrydockLeaseReacquireLogType::LOGCONST,
        array(
          'class' => get_class($ex),
          'message' => $ex->getMessage(),
        ));

      $this->yieldLease($lease, $ex);
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
    $resources = $this->loadAcquirableResourcesForLease($blueprints, $lease);

    list($free_resources, $used_resources) = $this->partitionResources(
      $lease,
      $resources);

    $resource = $this->leaseAnyResource($lease, $free_resources);
    if ($resource) {
      return $resource;
    }

    // We're about to try creating a resource. If we're already creating
    // something, just yield until that resolves.

    $this->yieldForPendingResources($lease);

    // We haven't been able to lease an existing resource yet, so now we try to
    // create one. We may still have some less-desirable "used" resources that
    // we'll sometimes try to lease later if we fail to allocate a new resource.

    $resource = $this->newLeasedResource($lease, $blueprints);
    if ($resource) {
      return $resource;
    }

    // We haven't been able to lease a desirable "free" resource or create a
    // new resource. Try to lease a "used" resource.

    $resource = $this->leaseAnyResource($lease, $used_resources);
    if ($resource) {
      return $resource;
    }

    // If this lease has already triggered a reclaim, just yield and wait for
    // it to resolve.
    $this->yieldForReclaimingResources($lease);

    // Try to reclaim a resource. This will yield if it reclaims something.
    $this->reclaimAnyResource($lease, $blueprints);

    // We weren't able to lease, create, or reclaim any resources. We just have
    // to wait for resources to become available.

    $lease->logEvent(
      DrydockLeaseWaitingForResourcesLogType::LOGCONST,
      array(
        'blueprintPHIDs' => mpull($blueprints, 'getPHID'),
      ));

    throw new PhabricatorWorkerYieldException(15);
  }

  private function reclaimAnyResource(DrydockLease $lease, array $blueprints) {
    assert_instances_of($blueprints, 'DrydockBlueprint');

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

        // Yield explicitly here: we'll be awakened when the resource is
        // reclaimed.

        throw new PhabricatorWorkerYieldException(15);
      }
    }
  }

  private function yieldForPendingResources(DrydockLease $lease) {
    // See T13677. If this lease has already triggered the allocation of
    // one or more resources and they are still pending, just yield and
    // wait for them.

    $viewer = $this->getViewer();

    $phids = $lease->getAllocatedResourcePHIDs();
    if (!$phids) {
      return null;
    }

    $resources = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->withStatuses(
        array(
          DrydockResourceStatus::STATUS_PENDING,
        ))
      ->setLimit(1)
      ->execute();
    if (!$resources) {
      return;
    }

    $lease->logEvent(
      DrydockLeaseWaitingForActivationLogType::LOGCONST,
      array(
        'resourcePHIDs' => mpull($resources, 'getPHID'),
      ));

    throw new PhabricatorWorkerYieldException(15);
  }

  private function yieldForReclaimingResources(DrydockLease $lease) {
    $viewer = $this->getViewer();

    $phids = $lease->getReclaimedResourcePHIDs();
    if (!$phids) {
      return;
    }

    $resources = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->withStatuses(
        array(
          DrydockResourceStatus::STATUS_ACTIVE,
          DrydockResourceStatus::STATUS_RELEASED,
        ))
      ->setLimit(1)
      ->execute();
    if (!$resources) {
      return;
    }

    $lease->logEvent(
      DrydockLeaseWaitingForReclamationLogType::LOGCONST,
      array(
        'resourcePHIDs' => mpull($resources, 'getPHID'),
      ));

    throw new PhabricatorWorkerYieldException(15);
  }

  private function newLeasedResource(
    DrydockLease $lease,
    array $blueprints) {
    assert_instances_of($blueprints, 'DrydockBlueprint');

    $usable_blueprints = $this->removeOverallocatedBlueprints(
      $blueprints,
      $lease);

    // If we get nothing back here, some blueprint claims it can eventually
    // satisfy the lease, just not right now. This is a temporary failure,
    // and we expect allocation to succeed eventually.

    // Return, try to lease a "used" resource, and continue from there.

    if (!$usable_blueprints) {
      return null;
    }

    $usable_blueprints = $this->rankBlueprints($usable_blueprints, $lease);

    $new_resources = $this->newResources($lease, $usable_blueprints);
    if (!$new_resources) {
      // If we were unable to create any new resources, return and
      // try to lease a "used" resource.
      return null;
    }

    $new_resources = $this->removeUnacquirableResources(
      $new_resources,
      $lease);
    if (!$new_resources) {
      // If we make it here, we just built a resource but aren't allowed
      // to acquire it. We expect this to happen if the resource prevents
      // acquisition until it activates, which is common when a resource
      // needs to perform setup steps.

      // Explicitly yield and wait for activation, since we don't want to
      // lease a "used" resource.

      throw new PhabricatorWorkerYieldException(15);
    }

    $resource = $this->leaseAnyResource($lease, $new_resources);
    if ($resource) {
      return $resource;
    }

    // We may not be able to lease a resource even if we just built it:
    // another process may snatch it up before we can lease it. This should
    // be rare, but is not concerning. Just try to build another resource.

    // We likely could try to build the next resource immediately, but err on
    // the side of caution and yield for now, at least until this code is
    // better vetted.

    throw new PhabricatorWorkerYieldException(15);
  }

  private function partitionResources(
    DrydockLease $lease,
    array $resources) {

    assert_instances_of($resources, 'DrydockResource');
    $viewer = $this->getViewer();

    $lease_statuses = array(
      DrydockLeaseStatus::STATUS_PENDING,
      DrydockLeaseStatus::STATUS_ACQUIRED,
      DrydockLeaseStatus::STATUS_ACTIVE,
    );

    // Partition resources into "free" resources (which we can try to lease
    // immediately) and "used" resources, which we can only to lease after we
    // fail to allocate a new resource.

    // "Free" resources are unleased and/or prefer reuse over allocation.
    // "Used" resources are leased and prefer allocation over reuse.

    $free_resources = array();
    $used_resources = array();

    foreach ($resources as $resource) {
      $blueprint = $resource->getBlueprint();

      if (!$blueprint->shouldAllocateSupplementalResource($resource, $lease)) {
        $free_resources[] = $resource;
        continue;
      }

      $leases = id(new DrydockLeaseQuery())
        ->setViewer($viewer)
        ->withResourcePHIDs(array($resource->getPHID()))
        ->withStatuses($lease_statuses)
        ->setLimit(1)
        ->execute();
      if (!$leases) {
        $free_resources[] = $resource;
        continue;
      }

      $used_resources[] = $resource;
    }

    return array($free_resources, $used_resources);
  }

  private function newResources(
    DrydockLease $lease,
    array $blueprints) {
    assert_instances_of($blueprints, 'DrydockBlueprint');

    $resources = array();
    $exceptions = array();
    foreach ($blueprints as $blueprint) {
      $caught = null;
      try {
        $resources[] = $this->allocateResource($blueprint, $lease);

        // Bail after allocating one resource, we don't need any more than
        // this.
        break;
      } catch (Exception $ex) {
        $caught = $ex;
      } catch (Throwable $ex) {
        $caught = $ex;
      }

      if ($caught) {
        // This failure is not normally expected, so log it. It can be
        // caused by something mundane and recoverable, however (see below
        // for discussion).

        // We log to the blueprint separately from the log to the lease:
        // the lease is not attached to a blueprint yet so the lease log
        // will not show up on the blueprint; more than one blueprint may
        // fail; and the lease is not really impacted (and won't log) if at
        // least one blueprint actually works.

        $blueprint->logEvent(
          DrydockResourceAllocationFailureLogType::LOGCONST,
          array(
            'class' => get_class($caught),
            'message' => $caught->getMessage(),
          ));

        $exceptions[] = $caught;
      }
    }

    if (!$resources) {
      // If one or more blueprints claimed that they would be able to allocate
      // resources but none are actually able to allocate resources, log the
      // failure and yield so we try again soon.

      // This can happen if some unexpected issue occurs during allocation
      // (for example, a call to build a VM fails for some reason) or if we
      // raced another allocator and the blueprint is now full.

      $ex = new PhutilAggregateException(
        pht(
          'All blueprints failed to allocate a suitable new resource when '.
          'trying to allocate lease ("%s").',
          $lease->getPHID()),
        $exceptions);

      $lease->logEvent(
        DrydockLeaseAllocationFailureLogType::LOGCONST,
        array(
          'class' => get_class($ex),
          'message' => $ex->getMessage(),
        ));

      return null;
    }

    return $resources;
  }


  private function leaseAnyResource(
    DrydockLease $lease,
    array $resources) {
    assert_instances_of($resources, 'DrydockResource');

    if (!$resources) {
      return null;
    }

    $resources = $this->rankResources($resources, $lease);

    $exceptions = array();
    $yields = array();

    $allocated = null;
    foreach ($resources as $resource) {
      try {
        $this->acquireLease($resource, $lease);
        $allocated = $resource;
        break;
      } catch (DrydockResourceLockException $ex) {
        // We need to lock the resource to actually acquire it. If we aren't
        // able to acquire the lock quickly enough, we can yield and try again
        // later.
        $yields[] = $ex;
      } catch (DrydockSlotLockException $ex) {
        // This also just indicates we ran into some kind of contention,
        // probably from another lease. Just yield.
        $yields[] = $ex;
      } catch (DrydockAcquiredBrokenResourceException $ex) {
        // If a resource was reclaimed or destroyed by the time we actually
        // got around to acquiring it, we just got unlucky.
        $yields[] = $ex;
      } catch (PhabricatorWorkerYieldException $ex) {
        // We can be told to yield, particularly by the supplemental allocator
        // trying to give us a supplemental resource.
        $yields[] = $ex;
      } catch (Exception $ex) {
        $exceptions[] = $ex;
      }
    }

    if ($allocated) {
      return $allocated;
    }

    if ($yields) {
      throw new PhabricatorWorkerYieldException(15);
    }

    throw new PhutilAggregateException(
      pht(
        'Unable to acquire lease "%s" on any resource.',
        $lease->getPHID()),
      $exceptions);
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

    $impls = DrydockBlueprintImplementation::getAllForAllocatingLease($lease);
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
  private function loadAcquirableResourcesForLease(
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

      $lease->addAllocatedResourcePHIDs(
        array(
          $resource->getPHID(),
        ));
      $lease->save();

      PhabricatorWorker::scheduleTask(
        'DrydockResourceUpdateWorker',
        array(
          'resourcePHID' => $resource->getPHID(),

          // This task will generally yield while the resource activates, so
          // wake it back up once the resource comes online. Most of the time,
          // we'll be able to lease the newly activated resource.
          'awakenOnActivation' => array(
            $this->getCurrentWorkerTaskID(),
          ),
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
      throw new DrydockAcquiredBrokenResourceException(
        pht(
          'Trying to activate lease ("%s") on a resource ("%s") in '.
          'the wrong status ("%s").',
          $lease->getPHID(),
          $resource->getPHID(),
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
