<?php

final class DrydockLease extends DrydockDAO
  implements PhabricatorPolicyInterface {

  protected $resourcePHID;
  protected $resourceType;
  protected $until;
  protected $ownerPHID;
  protected $authorizingPHID;
  protected $attributes = array();
  protected $status = DrydockLeaseStatus::STATUS_PENDING;

  private $resource = self::ATTACHABLE;
  private $unconsumedCommands = self::ATTACHABLE;

  private $releaseOnDestruction;
  private $isAcquired = false;
  private $isActivated = false;
  private $activateWhenAcquired = false;
  private $slotLocks = array();

  public static function initializeNewLease() {
    $lease = new DrydockLease();

    // Pregenerate a PHID so that the caller can set something up to release
    // this lease before queueing it for activation.
    $lease->setPHID($lease->generatePHID());

    return $lease;
  }

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
    if (!$this->releaseOnDestruction) {
      return;
    }

    if (!$this->canRelease()) {
      return;
    }

    $actor = PhabricatorUser::getOmnipotentUser();
    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    $command = DrydockCommand::initializeNewCommand($actor)
      ->setTargetPHID($this->getPHID())
      ->setAuthorPHID($drydock_phid)
      ->setCommand(DrydockCommand::COMMAND_RELEASE)
      ->save();

    $this->scheduleUpdate();
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
        'status' => 'text32',
        'until' => 'epoch?',
        'resourceType' => 'text128',
        'ownerPHID' => 'phid?',
        'resourcePHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_resource' => array(
          'columns' => array('resourcePHID', 'status'),
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

  public function getUnconsumedCommands() {
    return $this->assertAttached($this->unconsumedCommands);
  }

  public function attachUnconsumedCommands(array $commands) {
    $this->unconsumedCommands = $commands;
    return $this;
  }

  public function isReleasing() {
    foreach ($this->getUnconsumedCommands() as $command) {
      if ($command->getCommand() == DrydockCommand::COMMAND_RELEASE) {
        return true;
      }
    }

    return false;
  }

  public function queueForActivation() {
    if ($this->getID()) {
      throw new Exception(
        pht('Only new leases may be queued for activation!'));
    }

    if (!$this->getAuthorizingPHID()) {
      throw new Exception(
        pht(
          'Trying to queue a lease for activation without an authorizing '.
          'object. Use "%s" to specify the PHID of the authorizing object. '.
          'The authorizing object must be approved to use the allowed '.
          'blueprints.',
          'setAuthorizingPHID()'));
    }

    if (!$this->getAllowedBlueprintPHIDs()) {
      throw new Exception(
        pht(
          'Trying to queue a lease for activation without any allowed '.
          'Blueprints. Use "%s" to specify allowed blueprints. The '.
          'authorizing object must be approved to use the allowed blueprints.',
          'setAllowedBlueprintPHIDs()'));
    }

    $this
      ->setStatus(DrydockLeaseStatus::STATUS_PENDING)
      ->save();

    $this->scheduleUpdate();

    $this->logEvent(DrydockLeaseQueuedLogType::LOGCONST);

    return $this;
  }

  public function isActivating() {
    switch ($this->getStatus()) {
      case DrydockLeaseStatus::STATUS_PENDING:
      case DrydockLeaseStatus::STATUS_ACQUIRED:
        return true;
    }

    return false;
  }

  public function isActive() {
    switch ($this->getStatus()) {
      case DrydockLeaseStatus::STATUS_ACTIVE:
        return true;
    }

    return false;
  }

  public function waitUntilActive() {
    while (true) {
      $lease = $this->reload();
      if (!$lease) {
        throw new Exception(pht('Failed to reload lease.'));
      }

      $status = $lease->getStatus();

      switch ($status) {
        case DrydockLeaseStatus::STATUS_ACTIVE:
          return;
        case DrydockLeaseStatus::STATUS_RELEASED:
          throw new Exception(pht('Lease has already been released!'));
        case DrydockLeaseStatus::STATUS_DESTROYED:
          throw new Exception(pht('Lease has already been destroyed!'));
        case DrydockLeaseStatus::STATUS_BROKEN:
          throw new Exception(pht('Lease has been broken!'));
        case DrydockLeaseStatus::STATUS_PENDING:
        case DrydockLeaseStatus::STATUS_ACQUIRED:
          break;
        default:
          throw new Exception(
            pht(
              'Lease has unknown status "%s".',
              $status));
      }

      sleep(1);
    }
  }

  public function setActivateWhenAcquired($activate) {
    $this->activateWhenAcquired = true;
    return $this;
  }

  public function needSlotLock($key) {
    $this->slotLocks[] = $key;
    return $this;
  }

  public function acquireOnResource(DrydockResource $resource) {
    $expect_status = DrydockLeaseStatus::STATUS_PENDING;
    $actual_status = $this->getStatus();
    if ($actual_status != $expect_status) {
      throw new Exception(
        pht(
          'Trying to acquire a lease on a resource which is in the wrong '.
          'state: status must be "%s", actually "%s".',
          $expect_status,
          $actual_status));
    }

    if ($this->activateWhenAcquired) {
      $new_status = DrydockLeaseStatus::STATUS_ACTIVE;
    } else {
      $new_status = DrydockLeaseStatus::STATUS_ACQUIRED;
    }

    if ($new_status == DrydockLeaseStatus::STATUS_ACTIVE) {
      if ($resource->getStatus() == DrydockResourceStatus::STATUS_PENDING) {
        throw new Exception(
          pht(
            'Trying to acquire an active lease on a pending resource. '.
            'You can not immediately activate leases on resources which '.
            'need time to start up.'));
      }
    }

    $this->openTransaction();

    try {
      DrydockSlotLock::acquireLocks($this->getPHID(), $this->slotLocks);
      $this->slotLocks = array();
    } catch (DrydockSlotLockException $ex) {
      $this->killTransaction();

      $this->logEvent(
        DrydockSlotLockFailureLogType::LOGCONST,
        array(
          'locks' => $ex->getLockMap(),
        ));

      throw $ex;
    }

    $this
      ->setResourcePHID($resource->getPHID())
      ->attachResource($resource)
      ->setStatus($new_status)
      ->save();

    $this->saveTransaction();

    $this->isAcquired = true;

    $this->logEvent(DrydockLeaseAcquiredLogType::LOGCONST);

    if ($new_status == DrydockLeaseStatus::STATUS_ACTIVE) {
      $this->didActivate();
    }

    return $this;
  }

  public function isAcquiredLease() {
    return $this->isAcquired;
  }

  public function activateOnResource(DrydockResource $resource) {
    $expect_status = DrydockLeaseStatus::STATUS_ACQUIRED;
    $actual_status = $this->getStatus();
    if ($actual_status != $expect_status) {
      throw new Exception(
        pht(
          'Trying to activate a lease which has the wrong status: status '.
          'must be "%s", actually "%s".',
          $expect_status,
          $actual_status));
    }

    if ($resource->getStatus() == DrydockResourceStatus::STATUS_PENDING) {
      // TODO: Be stricter about this?
      throw new Exception(
        pht(
          'Trying to activate a lease on a pending resource.'));
    }

    $this->openTransaction();

    try {
      DrydockSlotLock::acquireLocks($this->getPHID(), $this->slotLocks);
      $this->slotLocks = array();
    } catch (DrydockSlotLockException $ex) {
      $this->killTransaction();

      $this->logEvent(
        DrydockSlotLockFailureLogType::LOGCONST,
        array(
          'locks' => $ex->getLockMap(),
        ));

      throw $ex;
    }

    $this
      ->setStatus(DrydockLeaseStatus::STATUS_ACTIVE)
      ->save();

    $this->saveTransaction();

    $this->isActivated = true;

    $this->didActivate();

    return $this;
  }

  public function isActivatedLease() {
    return $this->isActivated;
  }

  public function canRelease() {
    if (!$this->getID()) {
      return false;
    }

    switch ($this->getStatus()) {
      case DrydockLeaseStatus::STATUS_RELEASED:
      case DrydockLeaseStatus::STATUS_DESTROYED:
        return false;
      default:
        return true;
    }
  }

  public function canReceiveCommands() {
    switch ($this->getStatus()) {
      case DrydockLeaseStatus::STATUS_RELEASED:
      case DrydockLeaseStatus::STATUS_DESTROYED:
        return false;
      default:
        return true;
    }
  }

  public function scheduleUpdate($epoch = null) {
    PhabricatorWorker::scheduleTask(
      'DrydockLeaseUpdateWorker',
      array(
        'leasePHID' => $this->getPHID(),
        'isExpireTask' => ($epoch !== null),
      ),
      array(
        'objectPHID' => $this->getPHID(),
        'delayUntil' => ($epoch ? (int)$epoch : null),
      ));
  }

  public function setAwakenTaskIDs(array $ids) {
    $this->setAttribute('internal.awakenTaskIDs', $ids);
    return $this;
  }

  public function setAllowedBlueprintPHIDs(array $phids) {
    $this->setAttribute('internal.blueprintPHIDs', $phids);
    return $this;
  }

  public function getAllowedBlueprintPHIDs() {
    return $this->getAttribute('internal.blueprintPHIDs', array());
  }

  private function didActivate() {
    $viewer = PhabricatorUser::getOmnipotentUser();
    $need_update = false;

    $this->logEvent(DrydockLeaseActivatedLogType::LOGCONST);

    $commands = id(new DrydockCommandQuery())
      ->setViewer($viewer)
      ->withTargetPHIDs(array($this->getPHID()))
      ->withConsumed(false)
      ->execute();
    if ($commands) {
      $need_update = true;
    }

    if ($need_update) {
      $this->scheduleUpdate();
    }

    $expires = $this->getUntil();
    if ($expires) {
      $this->scheduleUpdate($expires);
    }

    $this->awakenTasks();
  }

  public function logEvent($type, array $data = array()) {
    $log = id(new DrydockLog())
      ->setEpoch(PhabricatorTime::getNow())
      ->setType($type)
      ->setData($data);

    $log->setLeasePHID($this->getPHID());

    $resource_phid = $this->getResourcePHID();
    if ($resource_phid) {
      $resource = $this->getResource();

      $log->setResourcePHID($resource->getPHID());
      $log->setBlueprintPHID($resource->getBlueprintPHID());
    }

    return $log->save();
  }

  /**
   * Awaken yielded tasks after a state change.
   *
   * @return this
   */
  public function awakenTasks() {
    $awaken_ids = $this->getAttribute('internal.awakenTaskIDs');
    if (is_array($awaken_ids) && $awaken_ids) {
      PhabricatorWorker::awakenTaskIDs($awaken_ids);
    }

    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    if ($this->getResource()) {
      return $this->getResource()->getPolicy($capability);
    }

    // TODO: Implement reasonable policies.

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
