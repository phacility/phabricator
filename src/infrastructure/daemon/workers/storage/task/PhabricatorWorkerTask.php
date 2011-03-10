<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorWorkerTask extends PhabricatorWorkerDAO {

  protected $taskClass;
  protected $leaseOwner;
  protected $leaseExpires;
  protected $priority;
  protected $failureCount;

  private $serverTime;
  private $localTime;

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
    if ($this->leaseOwner) {
      $current_server_time = $this->serverTime + (time() - $this->localTime);
      if ($current_server_time >= $this->leaseExpires) {
        throw new Exception("Trying to update task after lease expiration!");
      }
    }

    return parent::save();
  }


}
