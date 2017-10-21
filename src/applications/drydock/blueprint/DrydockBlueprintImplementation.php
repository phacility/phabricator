<?php

/**
 * @task lease Lease Acquisition
 * @task resource Resource Allocation
 * @task interface Resource Interfaces
 * @task log Logging
 */
abstract class DrydockBlueprintImplementation extends Phobject {

  abstract public function getType();

  abstract public function isEnabled();

  abstract public function getBlueprintName();
  abstract public function getDescription();

  public function getBlueprintIcon() {
    return 'fa-map-o';
  }

  public function getFieldSpecifications() {
    $fields = array();

    $fields += $this->getCustomFieldSpecifications();

    if ($this->shouldUseConcurrentResourceLimit()) {
      $fields += array(
        'allocator.limit' => array(
          'name' => pht('Limit'),
          'caption' => pht(
            'Maximum number of resources this blueprint can have active '.
            'concurrently.'),
          'type' => 'int',
        ),
      );
    }

    return $fields;
  }

  protected function getCustomFieldSpecifications() {
    return array();
  }

  public function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }


/* -(  Lease Acquisition  )-------------------------------------------------- */


  /**
   * Enforce basic checks on lease/resource compatibility. Allows resources to
   * reject leases if they are incompatible, even if the resource types match.
   *
   * For example, if a resource represents a 32-bit host, this method might
   * reject leases that need a 64-bit host. The blueprint might also reject
   * a resource if the lease needs 8GB of RAM and the resource only has 6GB
   * free.
   *
   * This method should not acquire locks or expect anything to be locked. This
   * is a coarse compatibility check between a lease and a resource.
   *
   * @param DrydockBlueprint Concrete blueprint to allocate for.
   * @param DrydockResource Candidate resource to allocate the lease on.
   * @param DrydockLease Pending lease that wants to allocate here.
   * @return bool True if the resource and lease are compatible.
   * @task lease
   */
  abstract public function canAcquireLeaseOnResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease);


  /**
   * Acquire a lease. Allows resources to perform setup as leases are brought
   * online.
   *
   * If acquisition fails, throw an exception.
   *
   * @param DrydockBlueprint Blueprint which built the resource.
   * @param DrydockResource Resource to acquire a lease on.
   * @param DrydockLease Requested lease.
   * @return void
   * @task lease
   */
  abstract public function acquireLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease);


  /**
   * @return void
   * @task lease
   */
  public function activateLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {
    throw new PhutilMethodNotImplementedException();
  }


  /**
   * React to a lease being released.
   *
   * This callback is primarily useful for automatically releasing resources
   * once all leases are released.
   *
   * @param DrydockBlueprint Blueprint which built the resource.
   * @param DrydockResource Resource a lease was released on.
   * @param DrydockLease Recently released lease.
   * @return void
   * @task lease
   */
  abstract public function didReleaseLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease);


  /**
   * Destroy any temporary data associated with a lease.
   *
   * If a lease creates temporary state while held, destroy it here.
   *
   * @param DrydockBlueprint Blueprint which built the resource.
   * @param DrydockResource Resource the lease is acquired on.
   * @param DrydockLease The lease being destroyed.
   * @return void
   * @task lease
   */
  abstract public function destroyLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease);


/* -(  Resource Allocation  )------------------------------------------------ */


  /**
   * Enforce fundamental implementation/lease checks. Allows implementations to
   * reject a lease which no concrete blueprint can ever satisfy.
   *
   * For example, if a lease only builds ARM hosts and the lease needs a
   * PowerPC host, it may be rejected here.
   *
   * This is the earliest rejection phase, and followed by
   * @{method:canEverAllocateResourceForLease}.
   *
   * This method should not actually check if a resource can be allocated
   * right now, or even if a blueprint which can allocate a suitable resource
   * really exists, only if some blueprint may conceivably exist which could
   * plausibly be able to build a suitable resource.
   *
   * @param DrydockLease Requested lease.
   * @return bool True if some concrete blueprint of this implementation's
   *   type might ever be able to build a resource for the lease.
   * @task resource
   */
  abstract public function canAnyBlueprintEverAllocateResourceForLease(
    DrydockLease $lease);


  /**
   * Enforce basic blueprint/lease checks. Allows blueprints to reject a lease
   * which they can not build a resource for.
   *
   * This is the second rejection phase. It follows
   * @{method:canAnyBlueprintEverAllocateResourceForLease} and is followed by
   * @{method:canAllocateResourceForLease}.
   *
   * This method should not check if a resource can be built right now, only
   * if the blueprint as configured may, at some time, be able to build a
   * suitable resource.
   *
   * @param DrydockBlueprint Blueprint which may be asked to allocate a
   *   resource.
   * @param DrydockLease Requested lease.
   * @return bool True if this blueprint can eventually build a suitable
   *   resource for the lease, as currently configured.
   * @task resource
   */
  abstract public function canEverAllocateResourceForLease(
    DrydockBlueprint $blueprint,
    DrydockLease $lease);


  /**
   * Enforce basic availability limits. Allows blueprints to reject resource
   * allocation if they are currently overallocated.
   *
   * This method should perform basic capacity/limit checks. For example, if
   * it has a limit of 6 resources and currently has 6 resources allocated,
   * it might reject new leases.
   *
   * This method should not acquire locks or expect locks to be acquired. This
   * is a coarse check to determine if the operation is likely to succeed
   * right now without needing to acquire locks.
   *
   * It is expected that this method will sometimes return `true` (indicating
   * that a resource can be allocated) but find that another allocator has
   * eaten up free capacity by the time it actually tries to build a resource.
   * This is normal and the allocator will recover from it.
   *
   * @param DrydockBlueprint The blueprint which may be asked to allocate a
   *   resource.
   * @param DrydockLease Requested lease.
   * @return bool True if this blueprint appears likely to be able to allocate
   *   a suitable resource.
   * @task resource
   */
  abstract public function canAllocateResourceForLease(
    DrydockBlueprint $blueprint,
    DrydockLease $lease);


  /**
   * Allocate a suitable resource for a lease.
   *
   * This method MUST acquire, hold, and manage locks to prevent multiple
   * allocations from racing. World state is not locked before this method is
   * called. Blueprints are entirely responsible for any lock handling they
   * need to perform.
   *
   * @param DrydockBlueprint The blueprint which should allocate a resource.
   * @param DrydockLease Requested lease.
   * @return DrydockResource Allocated resource.
   * @task resource
   */
  abstract public function allocateResource(
    DrydockBlueprint $blueprint,
    DrydockLease $lease);


  /**
   * @task resource
   */
  public function activateResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource) {
    throw new PhutilMethodNotImplementedException();
  }


  /**
   * Destroy any temporary data associated with a resource.
   *
   * If a resource creates temporary state when allocated, destroy that state
   * here. For example, you might shut down a virtual host or destroy a working
   * copy on disk.
   *
   * @param DrydockBlueprint Blueprint which built the resource.
   * @param DrydockResource Resource being destroyed.
   * @return void
   * @task resource
   */
  abstract public function destroyResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource);


  /**
   * Get a human readable name for a resource.
   *
   * @param DrydockBlueprint Blueprint which built the resource.
   * @param DrydockResource Resource to get the name of.
   * @return string Human-readable resource name.
   * @task resource
   */
  abstract public function getResourceName(
    DrydockBlueprint $blueprint,
    DrydockResource $resource);


/* -(  Resource Interfaces  )------------------------------------------------ */


  abstract public function getInterface(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease,
    $type);


/* -(  Logging  )------------------------------------------------------------ */


  public static function getAllBlueprintImplementations() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }

  public static function getNamedImplementation($class) {
    return idx(self::getAllBlueprintImplementations(), $class);
  }

  protected function newResourceTemplate(DrydockBlueprint $blueprint) {

    $resource = id(new DrydockResource())
      ->setBlueprintPHID($blueprint->getPHID())
      ->attachBlueprint($blueprint)
      ->setType($this->getType())
      ->setStatus(DrydockResourceStatus::STATUS_PENDING);

    // Pre-allocate the resource PHID.
    $resource->setPHID($resource->generatePHID());

    return $resource;
  }

  protected function newLease(DrydockBlueprint $blueprint) {
    return DrydockLease::initializeNewLease()
      ->setAuthorizingPHID($blueprint->getPHID());
  }

  protected function requireActiveLease(DrydockLease $lease) {
    $lease_status = $lease->getStatus();

    switch ($lease_status) {
      case DrydockLeaseStatus::STATUS_PENDING:
      case DrydockLeaseStatus::STATUS_ACQUIRED:
        throw new PhabricatorWorkerYieldException(15);
      case DrydockLeaseStatus::STATUS_ACTIVE:
        return;
      default:
        throw new Exception(
          pht(
            'Lease ("%s") is in bad state ("%s"), expected "%s".',
            $lease->getPHID(),
            $lease_status,
            DrydockLeaseStatus::STATUS_ACTIVE));
    }
  }


  /**
   * Does this implementation use concurrent resource limits?
   *
   * Implementations can override this method to opt into standard limit
   * behavior, which provides a simple concurrent resource limit.
   *
   * @return bool True to use limits.
   */
  protected function shouldUseConcurrentResourceLimit() {
    return false;
  }


  /**
   * Get the effective concurrent resource limit for this blueprint.
   *
   * @param DrydockBlueprint Blueprint to get the limit for.
   * @return int|null Limit, or `null` for no limit.
   */
  protected function getConcurrentResourceLimit(DrydockBlueprint $blueprint) {
    if ($this->shouldUseConcurrentResourceLimit()) {
      $limit = $blueprint->getFieldValue('allocator.limit');
      $limit = (int)$limit;
      if ($limit > 0) {
        return $limit;
      } else {
        return null;
      }
    }

    return null;
  }


  protected function getConcurrentResourceLimitSlotLock(
    DrydockBlueprint $blueprint) {

    $limit = $this->getConcurrentResourceLimit($blueprint);
    if ($limit === null) {
      return;
    }

    $blueprint_phid = $blueprint->getPHID();

    // TODO: This logic shouldn't do anything awful, but is a little silly. It
    // would be nice to unify the "huge limit" and "small limit" cases
    // eventually but it's a little tricky.

    // If the limit is huge, just pick a random slot. This is just stopping
    // us from exploding if someone types a billion zillion into the box.
    if ($limit > 1024) {
      $slot = mt_rand(0, $limit - 1);
      return "allocator({$blueprint_phid}).limit({$slot})";
    }

    // For reasonable limits, actually check for an available slot.
    $locks = DrydockSlotLock::loadLocks($blueprint_phid);
    $locks = mpull($locks, null, 'getLockKey');

    $slots = range(0, $limit - 1);
    shuffle($slots);

    foreach ($slots as $slot) {
      $slot_lock = "allocator({$blueprint_phid}).limit({$slot})";
      if (empty($locks[$slot_lock])) {
        return $slot_lock;
      }
    }

    // If we found no free slot, just return whatever we checked last (which
    // is just a random slot). There's a small chance we'll get lucky and the
    // lock will be free by the time we try to take it, but usually we'll just
    // fail to grab the lock, throw an appropriate lock exception, and get back
    // on the right path to retry later.
    return $slot_lock;
  }



  /**
   * Apply standard limits on resource allocation rate.
   *
   * @param DrydockBlueprint The blueprint requesting an allocation.
   * @return bool True if further allocations should be limited.
   */
  protected function shouldLimitAllocatingPoolSize(
    DrydockBlueprint $blueprint) {

    // TODO: If this mechanism sticks around, these values should be
    // configurable by the blueprint implementation.

    // Limit on total number of active resources.
    $total_limit = $this->getConcurrentResourceLimit($blueprint);

    // Always allow at least this many allocations to be in flight at once.
    $min_allowed = 1;

    // Allow this fraction of allocating resources as a fraction of active
    // resources.
    $growth_factor = 0.25;

    $resource = new DrydockResource();
    $conn_r = $resource->establishConnection('r');

    $counts = queryfx_all(
      $conn_r,
      'SELECT status, COUNT(*) N FROM %T
        WHERE blueprintPHID = %s AND status != %s
        GROUP BY status',
      $resource->getTableName(),
      $blueprint->getPHID(),
      DrydockResourceStatus::STATUS_DESTROYED);
    $counts = ipull($counts, 'N', 'status');

    $n_alloc = idx($counts, DrydockResourceStatus::STATUS_PENDING, 0);
    $n_active = idx($counts, DrydockResourceStatus::STATUS_ACTIVE, 0);
    $n_broken = idx($counts, DrydockResourceStatus::STATUS_BROKEN, 0);
    $n_released = idx($counts, DrydockResourceStatus::STATUS_RELEASED, 0);

    // If we're at the limit on total active resources, limit additional
    // allocations.
    if ($total_limit !== null) {
      $n_total = ($n_alloc + $n_active + $n_broken + $n_released);
      if ($n_total >= $total_limit) {
        return true;
      }
    }

    // If the number of in-flight allocations is fewer than the minimum number
    // of allowed allocations, don't impose a limit.
    if ($n_alloc < $min_allowed) {
      return false;
    }

    $allowed_alloc = (int)ceil($n_active * $growth_factor);

    // If the number of in-flight allocation is fewer than the number of
    // allowed allocations according to the pool growth factor, don't impose
    // a limit.
    if ($n_alloc < $allowed_alloc) {
      return false;
    }

    return true;
  }

}
