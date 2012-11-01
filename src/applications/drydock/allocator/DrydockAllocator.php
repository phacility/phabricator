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

final class DrydockAllocator {

  private $resourceType;
  private $lease;

  public function setResourceType($resource_type) {
    $this->resourceType = $resource_type;
    return $this;
  }

  public function getResourceType() {
    return $this->resourceType;
  }

  public function getPendingLease() {
    if (!$this->lease) {
      $lease = new DrydockLease();
      $lease->setStatus(DrydockLeaseStatus::STATUS_PENDING);
      $lease->save();

      $this->lease = $lease;
    }
    return $lease;
  }

  public function allocate() {
    $lease = $this->getPendingLease();

    $data = array(
      'type'        => $this->getResourceType(),
      'lease'       => $lease->getID(),
    );

    $task = PhabricatorWorker::scheduleTask('DrydockAllocatorWorker', $data);

    $lease->setTaskID($task->getID());

    return $lease;
  }

}
