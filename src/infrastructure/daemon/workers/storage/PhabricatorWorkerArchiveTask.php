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

final class PhabricatorWorkerArchiveTask extends PhabricatorWorkerTask {

  const RESULT_SUCCESS    = 0;
  const RESULT_FAILURE    = 1;
  const RESULT_CANCELLED  = 2;

  protected $duration;
  protected $result;

  public function save() {
    if (!$this->getID()) {
      throw new Exception(
        "Trying to archive a task with no ID.");
    }

    $other = new PhabricatorWorkerActiveTask();
    $conn_w = $this->establishConnection('w');

    $this->openTransaction();
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE id = %d',
        $other->getTableName(),
        $this->getID());
      $result = parent::insert();
    $this->saveTransaction();

    return $result;
  }

  public function delete() {
    $this->openTransaction();
      if ($this->getDataID()) {
        $conn_w = $this->establishConnection('w');
        $data_table = new PhabricatorWorkerTaskData();

        queryfx(
          $conn_w,
          'DELETE FROM %T WHERE id = %d',
          $data_table->getTableName(),
          $this->getDataID());
      }

      $result = parent::delete();
    $this->saveTransaction();
    return $result;
  }

  public function unarchiveTask() {
    $this->openTransaction();
      $active = id(new PhabricatorWorkerActiveTask())
        ->setID($this->getID())
        ->setTaskClass($this->getTaskClass())
        ->setLeaseOwner(null)
        ->setLeaseExpires(0)
        ->setFailureCount(0)
        ->setDataID($this->getDataID())
        ->insert();

      $this->setDataID(null);
      $this->delete();
    $this->saveTransaction();

    return $active;
  }

}
