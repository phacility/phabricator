<?php

final class DrydockAllocatorWorker extends PhabricatorWorker {

  private function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  private function loadLease() {
    $viewer = $this->getViewer();

    // TODO: Make the task data a dictionary like every other worker, and
    // probably make this a PHID.
    $lease_id = $this->getTaskData();

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withIDs(array($lease_id))
      ->executeOne();
    if (!$lease) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No such lease "%s"!', $lease_id));
    }

    return $lease;
  }

  protected function doWork() {
    $lease = $this->loadLease();
    $this->allocateLease($lease);
  }

  private function allocateLease(DrydockLease $lease) {
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
          $resources[] = $blueprint->allocateResource($lease);
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
        $blueprint->allocateLease($resource, $lease);
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
   * Load a list of all resources which a given lease can possibly be
   * allocated against.
   *
   * @param list<DrydockBlueprint> Blueprints which may produce suitable
   *   resources.
   * @param DrydockLease Requested lease.
   * @return list<DrydockResource> Resources which may be able to allocate
   *   the lease.
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
          DrydockResourceStatus::STATUS_OPEN,
        ))
      ->execute();

    $keep = array();
    foreach ($resources as $key => $resource) {
      if (!$resource->canAllocateLease($lease)) {
        continue;
      }

      $keep[$key] = $resource;
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
   */
  private function rankResources(array $resources, DrydockLease $lease) {
    assert_instances_of($resources, 'DrydockResource');

    // TODO: Implement improvements to this ranking algorithm if they become
    // available.
    shuffle($resources);

    return $resources;
  }


  /**
   * Get all the concrete @{class:DrydockBlueprint}s which can possibly
   * build a resource to satisfy a lease.
   *
   * @param DrydockLease Requested lease.
   * @return list<DrydockBlueprint> List of qualifying blueprints.
   */
  private function loadBlueprintsForAllocatingLease(
    DrydockLease $lease) {
    $viewer = $this->getViewer();

    $impls = $this->loadBlueprintImplementationsForAllocatingLease($lease);
    if (!$impls) {
      return array();
    }

    // TODO: When blueprints can be disabled, this query should ignore disabled
    // blueprints.

    $blueprints = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withBlueprintClasses(array_keys($impls))
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
   * Remove blueprints which are too heavily allocated to build a resource for
   * a lease from a list of blueprints.
   *
   * @param list<DrydockBlueprint> List of blueprints.
   * @param list<DrydockBlueprint> List with fully allocated blueprints
   *   removed.
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

}
