<?php

/**
 * @task lease      Lease Acquisition
 * @task resource   Resource Allocation
 * @task log        Logging
 */
abstract class DrydockBlueprintImplementation {

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
   * @task lease
   */
  final public function filterResource(
    DrydockResource $resource,
    DrydockLease $lease) {

    $scope = $this->pushActiveScope($resource, $lease);

    return $this->canAllocateLease($resource, $lease);
  }


  /**
   * Enforce basic checks on lease/resource compatibility. Allows resources to
   * reject leases if they are incompatible, even if the resource types match.
   *
   * For example, if a resource represents a 32-bit host, this method might
   * reject leases that need a 64-bit host. If a resource represents a working
   * copy of repository "X", this method might reject leases which need a
   * working copy of repository "Y". Generally, although the main types of
   * a lease and resource may match (e.g., both "host"), it may not actually be
   * possible to satisfy the lease with a specific resource.
   *
   * This method generally should not enforce limits or perform capacity
   * checks. Perform those in @{method:shouldAllocateLease} instead. It also
   * should not perform actual acquisition of the lease; perform that in
   * @{method:executeAcquireLease} instead.
   *
   * @param   DrydockResource   Candidiate resource to allocate the lease on.
   * @param   DrydockLease      Pending lease that wants to allocate here.
   * @return  bool              True if the resource and lease are compatible.
   * @task lease
   */
  abstract protected function canAllocateLease(
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
      $this->log('Allocated Lease');
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


  public function canAllocateMoreResources(array $pool) {
    return true;
  }

  abstract protected function executeAllocateResource(DrydockLease $lease);


  final public function allocateResource(DrydockLease $lease) {
    $scope = $this->pushActiveScope(null, $lease);

    $this->log(
      pht(
        "Blueprint '%s': Allocating Resource for '%s'",
        $this->getBlueprintClass(),
        $lease->getLeaseName()));

    try {
      $resource = $this->executeAllocateResource($lease);
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
    static $list = null;

    if ($list === null) {
      $blueprints = id(new PhutilSymbolLoader())
        ->setType('class')
        ->setAncestorClass(__CLASS__)
        ->setConcreteOnly(true)
        ->selectAndLoadSymbols();
      $list = ipull($blueprints, 'name', 'name');
      foreach ($list as $class_name => $ignored) {
        $list[$class_name] = newv($class_name, array());
      }
    }

    return $list;
  }

  public static function getAllBlueprintImplementationsForResource($type) {
    static $groups = null;
    if ($groups === null) {
      $groups = mgroup(self::getAllBlueprintImplementations(), 'getType');
    }
    return idx($groups, $type, array());
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
