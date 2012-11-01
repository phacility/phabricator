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

final class PhabricatorWorkerActiveTask extends PhabricatorWorkerTask {

  private $serverTime;
  private $localTime;

  public function getTableName() {
    return 'worker_task';
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function setServerTime($server_time) {
    $this->serverTime = $server_time;
    $this->localTime = time();
    return $this;
  }

  public function setLeaseDuration($lease_duration) {
    $server_lease_expires = $this->serverTime + $lease_duration;
    $this->setLeaseExpires($server_lease_expires);
    return $this->save();
  }

  public function save() {
    $this->checkLease();

    $is_new = !$this->getID();
    if ($is_new) {
      $this->failureCount = 0;
    }

    if ($is_new && $this->getData()) {
      $data = new PhabricatorWorkerTaskData();
      $data->setData($this->getData());
      $data->save();

      $this->setDataID($data->getID());
    }

    return parent::save();
  }

  protected function checkLease() {
    if ($this->leaseOwner) {
      $current_server_time = $this->serverTime + (time() - $this->localTime);
      if ($current_server_time >= $this->leaseExpires) {
        throw new Exception("Trying to update task after lease expiration!");
      }
    }
  }

  public function delete() {
    throw new Exception(
      "Active tasks can not be deleted directly. ".
      "Use archiveTask() to move tasks to the archive.");
  }

  public function archiveTask($result, $duration) {
    if (!$this->getID()) {
      throw new Exception(
        "Attempting to archive a task which hasn't been save()d!");
    }

    $this->checkLease();

    $archive = id(new PhabricatorWorkerArchiveTask())
      ->setID($this->getID())
      ->setTaskClass($this->getTaskClass())
      ->setLeaseOwner($this->getLeaseOwner())
      ->setLeaseExpires($this->getLeaseExpires())
      ->setFailureCount($this->getFailureCount())
      ->setDataID($this->getDataID())
      ->setResult($result)
      ->setDuration($duration);

    // NOTE: This deletes the active task (this object)!
    $archive->save();

    return $archive;
  }

}
