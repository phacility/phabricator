<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

abstract class DrydockBlueprint {

  private $activeLease;
  private $activeResource;

  abstract public function getType();
  abstract public function getInterface(
    DrydockResource $resource,
    DrydockLease $lease,
    $type);

  protected function executeAcquireLease(
    DrydockResource $resource,
    DrydockLease $lease) {
    return;
  }

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

  public function canAllocateResources() {
    return false;
  }

  protected function executeAllocateResource(DrydockLease $lease) {
    throw new Exception("This blueprint can not allocate resources!");
  }

  final public function allocateResource(DrydockLease $lease) {
    $this->activeLease = $lease;
    $this->activeResource = null;

    $this->log('Allocating Resource');

    try {
      $resource = $this->executeAllocateResource($lease);
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
    $resource->setBlueprintClass(get_class($this));
    $resource->setType($this->getType());
    $resource->setStatus(DrydockResourceStatus::STATUS_PENDING);
    $resource->setName($name);
    $resource->save();

    $this->activeResource = $resource;
    $this->log('New Template');

    return $resource;
  }


}
