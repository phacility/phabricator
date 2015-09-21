<?php

/**
 * @task lease      Lease Acquisition
 * @task resource   Resource Allocation
 * @task log        Logging
 */
abstract class DrydockBlueprintImplementation extends Phobject {

  private $activeResource;
  private $activeLease;
  private $instance;

  abstract public function getType();
  abstract public function getInterface(
    DrydockResource $resource,
    DrydockLease $lease,
    $type);

  abstract public function isEnabled();

  abstract public function getBlueprintName();
  abstract public function getDescription();

  public function getBlueprintClass() {
    return get_class($this);
  }

  protected function loadLease($lease_id) {
    // TODO: Get rid of this?
    $query = id(new DrydockLeaseQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($lease_id))
      ->execute();

    $lease = idx($query, $lease_id);

    if (!$lease) {
      throw new Exception(pht("No such lease '%d'!", $lease_id));
    }

    return $lease;
  }

  protected function getInstance() {
    if (!$this->instance) {
      throw new Exception(
        pht('Attach the blueprint instance to the implementation.'));
    }

    return $this->instance;
  }

  public function attachInstance(DrydockBlueprint $instance) {
    $this->instance = $instance;
    return $this;
  }

  public function getFieldSpecifications() {
    return array();
  }

  public function getDetail($key, $default = null) {
    return $this->getInstance()->getDetail($key, $default);
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
   * @param DrydockResource Candidiate resource to allocate the lease on.
   * @param DrydockLease Pending lease that wants to allocate here.
   * @return bool True if the resource and lease are compatible.
   * @task lease
   */
  abstract public function canAllocateLeaseOnResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease);


  /**
   * @task lease
   */
  final public function allocateLease(
    DrydockResource $resource,
    DrydockLease $lease) {

    $scope = $this->pushActiveScope($resource, $lease);

    $this->log(pht('Trying to Allocate Lease'));

    $lease->setStatus(DrydockLeaseStatus::STATUS_ACQUIRING);
    $lease->setResourceID($resource->getID());
    $lease->attachResource($resource);

    $ephemeral_lease = id(clone $lease)->makeEphemeral();

    $allocated = false;
    $allocation_exception = null;

    $resource->openTransaction();
      $resource->beginReadLocking();
        $resource->reload();

        // TODO: Policy stuff.
        $other_leases = id(new DrydockLease())->loadAllWhere(
          'status IN (%Ld) AND resourceID = %d',
          array(
            DrydockLeaseStatus::STATUS_ACQUIRING,
            DrydockLeaseStatus::STATUS_ACTIVE,
          ),
          $resource->getID());

        try {
          $allocated = $this->shouldAllocateLease(
            $resource,
            $ephemeral_lease,
            $other_leases);
        } catch (Exception $ex) {
          $allocation_exception = $ex;
        }

        if ($allocated) {
          $lease->save();
        }
      $resource->endReadLocking();
    if ($allocated) {
      $resource->saveTransaction();
      $this->log(pht('Allocated Lease'));
    } else {
      $resource->killTransaction();
      $this->log(pht('Failed to Allocate Lease'));
    }

    if ($allocation_exception) {
      $this->logException($allocation_exception);
    }

    return $allocated;
  }


  /**
   * Enforce lease limits on resources. Allows resources to reject leases if
   * they would become over-allocated by accepting them.
   *
   * For example, if a resource represents disk space, this method might check
   * how much space the lease is asking for (say, 200MB) and how much space is
   * left unallocated on the resource. It could grant the lease (return true)
   * if it has enough remaining space (more than 200MB), and reject the lease
   * (return false) if it does not (less than 200MB).
   *
   * A resource might also allow only exclusive leases. In this case it could
   * accept a new lease (return true) if there are no active leases, or reject
   * the new lease (return false) if there any other leases.
   *
   * A lock is held on the resource while this method executes to prevent
   * multiple processes from allocating leases on the resource simultaneously.
   * However, this means you should implement the method as cheaply as possible.
   * In particular, do not perform any actual acquisition or setup in this
   * method.
   *
   * If allocation is permitted, the lease will be moved to `ACQUIRING` status
   * and @{method:executeAcquireLease} will be called to actually perform
   * acquisition.
   *
   * General compatibility checks unrelated to resource limits and capacity are
   * better implemented in @{method:canAllocateLease}, which serves as a
   * cheap filter before lock acquisition.
   *
   * @param   DrydockResource     Candidate resource to allocate the lease on.
   * @param   DrydockLease        Pending lease that wants to allocate here.
   * @param   list<DrydockLease>  Other allocated and acquired leases on the
   *                              resource. The implementation can inspect them
   *                              to verify it can safely add the new lease.
   * @return  bool                True to allocate the lease on the resource;
   *                              false to reject it.
   * @task lease
   */
  abstract protected function shouldAllocateLease(
    DrydockResource $resource,
    DrydockLease $lease,
    array $other_leases);


  /**
   * @task lease
   */
  final public function acquireLease(
    DrydockResource $resource,
    DrydockLease $lease) {

    $scope = $this->pushActiveScope($resource, $lease);

    $this->log(pht('Acquiring Lease'));
    $lease->setStatus(DrydockLeaseStatus::STATUS_ACTIVE);
    $lease->setResourceID($resource->getID());
    $lease->attachResource($resource);

    $ephemeral_lease = id(clone $lease)->makeEphemeral();

    try {
      $this->executeAcquireLease($resource, $ephemeral_lease);
    } catch (Exception $ex) {
      $this->logException($ex);
      throw $ex;
    }

    $lease->setAttributes($ephemeral_lease->getAttributes());
    $lease->save();
    $this->log(pht('Acquired Lease'));
  }


  /**
   * Acquire and activate an allocated lease. Allows resources to peform setup
   * as leases are brought online.
   *
   * Following a successful call to @{method:canAllocateLease}, a lease is moved
   * to `ACQUIRING` status and this method is called after resource locks are
   * released. Nothing is locked while this method executes; the implementation
   * is free to perform expensive operations like writing files and directories,
   * executing commands, etc.
   *
   * After this method executes, the lease status is moved to `ACTIVE` and the
   * original leasee may access it.
   *
   * If acquisition fails, throw an exception.
   *
   * @param   DrydockResource   Resource to acquire a lease on.
   * @param   DrydockLease      Lease to acquire.
   * @return  void
   */
  abstract protected function executeAcquireLease(
    DrydockResource $resource,
    DrydockLease $lease);



  final public function releaseLease(
    DrydockResource $resource,
    DrydockLease $lease) {
    $scope = $this->pushActiveScope(null, $lease);

    $released = false;

    $lease->openTransaction();
      $lease->beginReadLocking();
        $lease->reload();

        if ($lease->getStatus() == DrydockLeaseStatus::STATUS_ACTIVE) {
          $lease->setStatus(DrydockLeaseStatus::STATUS_RELEASED);
          $lease->save();
          $released = true;
        }

      $lease->endReadLocking();
    $lease->saveTransaction();

    if (!$released) {
      throw new Exception(pht('Unable to release lease: lease not active!'));
    }

  }



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
   */
  abstract protected function executeAllocateResource(
    DrydockBlueprint $blueprint,
    DrydockLease $lease);

  final public function allocateResource(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {

    $scope = $this->pushActiveScope(null, $lease);

    $this->log(
      pht(
        "Blueprint '%s': Allocating Resource for '%s'",
        $this->getBlueprintClass(),
        $lease->getLeaseName()));

    try {
      $resource = $this->executeAllocateResource($blueprint, $lease);
      $this->validateAllocatedResource($resource);
    } catch (Exception $ex) {
      $this->logException($ex);
      throw $ex;
    }

    return $resource;
  }


/* -(  Logging  )------------------------------------------------------------ */


  /**
   * @task log
   */
  protected function logException(Exception $ex) {
    $this->log($ex->getMessage());
  }


  /**
   * @task log
   */
  protected function log($message) {
    self::writeLog(
      $this->activeResource,
      $this->activeLease,
      $message);
  }


  /**
   * @task log
   */
  public static function writeLog(
    DrydockResource $resource = null,
    DrydockLease $lease = null,
    $message = null) {

    $log = id(new DrydockLog())
      ->setEpoch(time())
      ->setMessage($message);

    if ($resource) {
      $log->setResourceID($resource->getID());
    }

    if ($lease) {
      $log->setLeaseID($lease->getID());
    }

    $log->save();
  }


  public static function getAllBlueprintImplementations() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }

  public static function getNamedImplementation($class) {
    return idx(self::getAllBlueprintImplementations(), $class);
  }

  protected function newResourceTemplate($name) {
    $resource = id(new DrydockResource())
      ->setBlueprintPHID($this->getInstance()->getPHID())
      ->setBlueprintClass($this->getBlueprintClass())
      ->setType($this->getType())
      ->setStatus(DrydockResourceStatus::STATUS_PENDING)
      ->setName($name)
      ->save();

    $this->activeResource = $resource;

    $this->log(
      pht(
        "Blueprint '%s': Created New Template",
        $this->getBlueprintClass()));

    return $resource;
  }

  /**
   * Sanity checks that the blueprint is implemented properly.
   */
  private function validateAllocatedResource($resource) {
    $blueprint = $this->getBlueprintClass();

    if (!($resource instanceof DrydockResource)) {
      throw new Exception(
        pht(
          "Blueprint '%s' is not properly implemented: %s must return an ".
          "object of type %s or throw, but returned something else.",
          $blueprint,
          'executeAllocateResource()',
          'DrydockResource'));
    }

    $current_status = $resource->getStatus();
    $req_status = DrydockResourceStatus::STATUS_OPEN;
    if ($current_status != $req_status) {
      $current_name = DrydockResourceStatus::getNameForStatus($current_status);
      $req_name = DrydockResourceStatus::getNameForStatus($req_status);
      throw new Exception(
        pht(
          "Blueprint '%s' is not properly implemented: %s must return a %s ".
          "with status '%s', but returned one with status '%s'.",
          $blueprint,
          'executeAllocateResource()',
          'DrydockResource',
          $req_name,
          $current_name));
    }
  }

  private function pushActiveScope(
    DrydockResource $resource = null,
    DrydockLease $lease = null) {

    if (($this->activeResource !== null) ||
        ($this->activeLease !== null)) {
      throw new Exception(pht('There is already an active resource or lease!'));
    }

    $this->activeResource = $resource;
    $this->activeLease = $lease;

    return new DrydockBlueprintScopeGuard($this);
  }

  public function popActiveScope() {
    $this->activeResource = null;
    $this->activeLease = null;
  }

}
