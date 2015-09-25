<?php

/**
 * @task allocate Allocator
 * @task resource Managing Resources
 * @task lease Managing Leases
 */
final class DrydockAllocatorWorker extends DrydockWorker {

  protected function doWork() {
    $lease_phid = $this->getTaskDataValue('leasePHID');
    $lease = $this->loadLease($lease_phid);

    $this->allocateAndAcquireLease($lease);
  }


/* -(  Allocator  )---------------------------------------------------------- */


  /**
   * Find or build a resource which can satisfy a given lease request, then
   * acquire the lease.
   *
   * @param DrydockLease Requested lease.
   * @return void
   * @task allocator
   */
  private function allocateAndAcquireLease(DrydockLease $lease) {
    $blueprints = $this->loadBlueprintsForAllocatingLease($lease);

    // If we get nothing back, that means no blueprint is defined which can
    // ever build the requested resource. This is a permanent failure, since
    // we don't expect to succeed no matter how many times we try.
    if (!$blueprints) {
      $lease
        ->setStatus(DrydockLeaseStatus::STATUS_BROKEN)
        ->save();
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
      if (!$blueprints) {
        // TODO: More formal temporary failure here. We should retry this
        // "soon" but not "immediately".
        throw new Exception(
          pht('No blueprints have space to allocate a resource right now.'));
      }

      $usable_blueprints = $this->rankBlueprints($blueprints, $lease);

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
        // TODO: We should distinguish between temporary and permament failures
        // here. If any blueprint failed temporarily, retry "soon". If none
        // of these failures were temporary, maybe this should be a permanent
        // failure?
        throw new PhutilAggregateException(
          pht(
            'All blueprints failed to allocate a suitable new resource when '.
            'trying to allocate lease "%s".',
            $lease->getPHID()),
          $exceptions);
      }

      // NOTE: We have not acquired the lease yet, so it is possible that the
      // resource we just built will be snatched up by some other lease before
      // we can. This is not problematic: we'll retry a little later and should
      // suceed eventually.
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
      // TODO: We should distinguish between temporary and permanent failures
      // here. If any failures were temporary (specifically, failed to acquire
      // locks)

      throw new PhutilAggregateException(
        pht(
          'Unable to acquire lease "%s" on any resouce.',
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

    $blueprints = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withBlueprintClasses(array_keys($impls))
      ->withDisabled(false)
      ->execute();

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


/* -(  Managing Resources  )------------------------------------------------- */


  /**
   * Perform an actual resource allocation with a particular blueprint.
   *
   * @param DrydockBlueprint The blueprint to allocate a resource from.
   * @param DrydockLease Requested lease.
   * @return DrydockResource Allocated resource.
   * @task resource
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
        'DrydockResourceWorker',
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
   * @task resource
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
      // TODO: Destroy the resource here?

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


/* -(  Managing Leases  )---------------------------------------------------- */


  /**
   * Perform an actual lease acquisition on a particular resource.
   *
   * @param DrydockResource Resource to acquire a lease on.
   * @param DrydockLease Lease to acquire.
   * @return void
   * @task lease
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
      PhabricatorWorker::scheduleTask(
        'DrydockLeaseWorker',
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
   * @task lease
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
      // TODO: Destroy the lease?
      throw new Exception(
        pht(
          'Blueprint "%s" (of type "%s") is not properly implemented: it '.
          'returned from "%s" with a lease acquired on the wrong resource.',
          $blueprint->getBlueprintName(),
          $blueprint->getClassName(),
          'acquireLease()'));
    }
  }


}
