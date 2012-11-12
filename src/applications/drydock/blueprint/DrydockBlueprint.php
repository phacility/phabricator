<?php

abstract class DrydockBlueprint {

  private $activeLease;
  private $activeResource;

  abstract public function getType();
  abstract public function getInterface(
    DrydockResource $resource,
    DrydockLease $lease,
    $type);

  abstract public function isEnabled();

  public function getBlueprintClass() {
    return get_class($this);
  }

  public function canAllocateMoreResources(array $pool) {
    return true;
  }

  abstract protected function executeAllocateResource(DrydockLease $lease);

  abstract protected function executeAcquireLease(
    DrydockResource $resource,
    DrydockLease $lease);

  final public function acquireLease(
    DrydockResource $resource,
    DrydockLease $lease) {

    $this->activeResource   = $resource;
    $this->activeLease      = $lease;

    $this->log('Acquiring Lease');
    try {
      $this->executeAcquireLease($resource, $lease);
    } catch (Exception $ex) {
      $this->logException($ex);
      $this->activeResource   = null;
      $this->activeLease      = null;

      throw $ex;
    }

    $lease->setResourceID($resource->getID());
    $lease->setStatus(DrydockLeaseStatus::STATUS_ACTIVE);
    $lease->save();

    $this->activeResource   = null;
    $this->activeLease      = null;
  }

  protected function logException(Exception $ex) {
    $this->log($ex->getMessage());
  }

  protected function log($message) {
    self::writeLog(
      $this->activeResource,
      $this->activeLease,
      $message);
  }

  public static function writeLog(
    DrydockResource $resource = null,
    DrydockLease $lease = null,
    $message) {

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

  final public function allocateResource(DrydockLease $lease) {
    $this->activeLease = $lease;
    $this->activeResource = null;

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
      $this->activeResource = null;

      throw $ex;
    }

    return $resource;
  }

  public static function getAllBlueprints() {
    static $list = null;

    if ($list === null) {
      $blueprints = id(new PhutilSymbolLoader())
        ->setType('class')
        ->setAncestorClass('DrydockBlueprint')
        ->setConcreteOnly(true)
        ->selectAndLoadSymbols();
      $list = ipull($blueprints, 'name', 'name');
      foreach ($list as $class_name => $ignored) {
        $list[$class_name] = newv($class_name, array());
      }
    }

    return $list;
  }

  public static function getAllBlueprintsForResource($type) {
    static $groups = null;
    if ($groups === null) {
      $groups = mgroup(self::getAllBlueprints(), 'getType');
    }
    return idx($groups, $type, array());
  }

  protected function newResourceTemplate($name) {
    $resource = new DrydockResource();
    $resource->setBlueprintClass($this->getBlueprintClass());
    $resource->setType($this->getType());
    $resource->setStatus(DrydockResourceStatus::STATUS_PENDING);
    $resource->setName($name);
    $resource->save();

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
        "Blueprint '{$blueprint}' is not properly implemented: ".
        "executeAllocateResource() must return an object of type ".
        "DrydockResource or throw, but returned something else.");
    }

    $current_status = $resource->getStatus();
    $req_status = DrydockResourceStatus::STATUS_OPEN;
    if ($current_status != $req_status) {
      $current_name = DrydockResourceStatus::getNameForStatus($current_status);
      $req_name = DrydockResourceStatus::getNameForStatus($req_status);
      throw new Exception(
        "Blueprint '{$blueprint}' is not properly implemented: ".
        "executeAllocateResource() must return a DrydockResource with ".
        "status '{$req_name}', but returned one with status ".
        "'{$current_name}'.");
    }
  }

}
