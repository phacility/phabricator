<?php

final class DrydockLease extends DrydockDAO
  implements PhabricatorPolicyInterface {

  protected $resourceID;
  protected $resourceType;
  protected $until;
  protected $ownerPHID;
  protected $attributes = array();
  protected $status = DrydockLeaseStatus::STATUS_PENDING;
  protected $taskID;

  private $resource = self::ATTACHABLE;
  private $releaseOnDestruction;

  /**
   * Flag this lease to be released when its destructor is called. This is
   * mostly useful if you have a script which acquires, uses, and then releases
   * a lease, as you don't need to explicitly handle exceptions to properly
   * release the lease.
   */
  public function releaseOnDestruction() {
    $this->releaseOnDestruction = true;
    return $this;
  }

  public function __destruct() {
    if ($this->releaseOnDestruction) {
      if ($this->isActive()) {
        $this->release();
      }
    }
  }

  public function getLeaseName() {
    return pht('Lease %d', $this->getID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'attributes'    => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'status' => 'uint32',
        'until' => 'epoch?',
        'resourceType' => 'text128',
        'taskID' => 'id?',
        'ownerPHID' => 'phid?',
        'resourceID' => 'id?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setAttribute($key, $value) {
    $this->attributes[$key] = $value;
    return $this;
  }

  public function getAttribute($key, $default = null) {
    return idx($this->attributes, $key, $default);
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(DrydockLeasePHIDType::TYPECONST);
  }

  public function getInterface($type) {
    return $this->getResource()->getInterface($this, $type);
  }

  public function getResource() {
    return $this->assertAttached($this->resource);
  }

  public function attachResource(DrydockResource $resource = null) {
    $this->resource = $resource;
    return $this;
  }

  public function hasAttachedResource() {
    return ($this->resource !== null);
  }

  public function loadResource() {
    return id(new DrydockResource())->loadOneWhere(
      'id = %d',
      $this->getResourceID());
  }

  public function queueForActivation() {
    if ($this->getID()) {
      throw new Exception(
        pht('Only new leases may be queued for activation!'));
    }

    $this->setStatus(DrydockLeaseStatus::STATUS_PENDING);
    $this->save();

    $task = PhabricatorWorker::scheduleTask(
      'DrydockAllocatorWorker',
      $this->getID());

    // NOTE: Scheduling the task might execute it in-process, if we're running
    // from a CLI script. Reload the lease to make sure we have the most
    // up-to-date information. Normally, this has no effect.
    $this->reload();

    $this->setTaskID($task->getID());
    $this->save();

    return $this;
  }

  public function release() {
    $this->assertActive();
    $this->setStatus(DrydockLeaseStatus::STATUS_RELEASED);
    $this->save();

    $this->resource = null;

    return $this;
  }

  public function isActive() {
    switch ($this->status) {
      case DrydockLeaseStatus::STATUS_ACTIVE:
      case DrydockLeaseStatus::STATUS_ACQUIRING:
        return true;
    }
    return false;
  }

  private function assertActive() {
    if (!$this->isActive()) {
      throw new Exception(
        pht(
          'Lease is not active! You can not interact with resources through '.
          'an inactive lease.'));
    }
  }

  public static function waitForLeases(array $leases) {
    assert_instances_of($leases, __CLASS__);

    $task_ids = array_filter(mpull($leases, 'getTaskID'));

    PhabricatorWorker::waitForTasks($task_ids);

    $unresolved = $leases;
    while (true) {
      foreach ($unresolved as $key => $lease) {
        $lease->reload();
        switch ($lease->getStatus()) {
          case DrydockLeaseStatus::STATUS_ACTIVE:
            unset($unresolved[$key]);
            break;
          case DrydockLeaseStatus::STATUS_RELEASED:
            throw new Exception(pht('Lease has already been released!'));
          case DrydockLeaseStatus::STATUS_EXPIRED:
            throw new Exception(pht('Lease has already expired!'));
          case DrydockLeaseStatus::STATUS_BROKEN:
            throw new Exception(pht('Lease has been broken!'));
          case DrydockLeaseStatus::STATUS_PENDING:
          case DrydockLeaseStatus::STATUS_ACQUIRING:
            break;
          default:
            throw new Exception(pht('Unknown status??'));
        }
      }

      if ($unresolved) {
        sleep(1);
      } else {
        break;
      }
    }

    foreach ($leases as $lease) {
      $lease->attachResource($lease->loadResource());
    }
  }

  public function waitUntilActive() {
    if (!$this->getID()) {
      $this->queueForActivation();
    }

    self::waitForLeases(array($this));
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    if ($this->getResource()) {
      return $this->getResource()->getPolicy($capability);
    }
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($this->getResource()) {
      return $this->getResource()->hasAutomaticCapability($capability, $viewer);
    }
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht('Leases inherit policies from the resources they lease.');
  }

}
