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

final class DrydockLease extends DrydockDAO {

  protected $phid;
  protected $resourceID;
  protected $until;
  protected $ownerPHID;
  protected $attributes = array();
  protected $status;

  private $resource;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'attributes'    => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_DRYL);
  }

  public function getInterface($type) {
    return $this->getResource()->getInterface($this, $type);
  }

  public function getResource() {
    $this->assertActive();
    if ($this->resource === null) {
      throw new Exception("Resource is not yet loaded.");
    }
    return $this->resource;
  }

  public function attachResource(DrydockResource $resource) {
    $this->assertActive();
    $this->resource = $resource;
    return $this;
  }

  public function loadResource() {
    $this->assertActive();
    return id(new DrydockResource())->loadOneWhere(
      'id = %d',
      $this->getResourceID());
  }

  public function release() {

    // TODO: Insert a cleanup task into the taskmaster queue.

    $this->setStatus(DrydockLeaseStatus::STATUS_RELEASED);
    $this->save();

    $this->resource = null;

    return $this;
  }

  private function assertActive() {
    if ($this->status != DrydockLeaseStatus::STATUS_ACTIVE) {
      throw new Exception(
        "Lease is not active! You can not interact with resources through ".
        "an inactive lease.");
    }
  }

  public function waitUntilActive() {
    while (true) {
      switch ($this->status) {
        case DrydockLeaseStatus::STATUS_ACTIVE:
          break 2;
        case DrydockLeaseStatus::STATUS_RELEASED:
        case DrydockLeaseStatus::STATUS_EXPIRED:
        case DrydockLeaseStatus::STATUS_BROKEN:
          throw new Exception("Lease will never become active!");
        case DrydockLeaseStatus::STATUS_PENDING:
          break;
      }
      sleep(2);
      $this->reload();
    }

    $this->attachResource($this->loadResource());

    return $this;
  }

}
