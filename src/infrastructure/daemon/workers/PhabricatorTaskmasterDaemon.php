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

final class PhabricatorTaskmasterDaemon extends PhabricatorDaemon {

  public function run() {
    $lease_ownership_name = $this->getLeaseOwnershipName();

    $task_table = new PhabricatorWorkerActiveTask();
    $taskdata_table = new PhabricatorWorkerTaskData();

    $sleep = 0;
    do {
      $this->log('Dequeuing a task...');

      $conn_w = $task_table->establishConnection('w');
      queryfx(
        $conn_w,
        'UPDATE %T SET leaseOwner = %s, leaseExpires = UNIX_TIMESTAMP() + 15
          WHERE leaseOwner IS NULL LIMIT 1',
          $task_table->getTableName(),
          $lease_ownership_name);
      $rows = $conn_w->getAffectedRows();

      if (!$rows) {
        $this->log('No unleased tasks. Dequeuing an expired lease...');
        queryfx(
          $conn_w,
          'UPDATE %T SET leaseOwner = %s, leaseExpires = UNIX_TIMESTAMP() + 15
            WHERE leaseExpires < UNIX_TIMESTAMP() LIMIT 1',
          $task_table->getTableName(),
          $lease_ownership_name);
        $rows = $conn_w->getAffectedRows();
      }

      if ($rows) {
        $data = queryfx_all(
          $conn_w,
          'SELECT task.*, taskdata.data _taskData, UNIX_TIMESTAMP() _serverTime
            FROM %T task LEFT JOIN %T taskdata
              ON taskdata.id = task.dataID
            WHERE leaseOwner = %s AND leaseExpires > UNIX_TIMESTAMP()
            LIMIT 1',
          $task_table->getTableName(),
          $taskdata_table->getTableName(),
          $lease_ownership_name);
        $tasks = $task_table->loadAllFromArray($data);
        $tasks = mpull($tasks, null, 'getID');

        $task_data = array();
        foreach ($data as $row) {
          $tasks[$row['id']]->setServerTime($row['_serverTime']);
          if ($row['_taskData']) {
            $task_data[$row['id']] = json_decode($row['_taskData'], true);
          } else {
            $task_data[$row['id']] = null;
          }
        }

        foreach ($tasks as $task) {
          $id = $task->getID();
          $class = $task->getTaskClass();

          $this->log("Working on task {$id} ({$class})...");

          // TODO: We should detect if we acquired a task with an expired lease
          // and log about it / bump up failure count.

          // TODO: We should detect if we acquired a task with an excessive
          // failure count and fail it permanently.

          $data = idx($task_data, $task->getID());
          try {
            if (!class_exists($class) ||
                !is_subclass_of($class, 'PhabricatorWorker')) {
              throw new Exception(
                "Task class '{$class}' does not extend PhabricatorWorker.");
            }
            $worker = newv($class, array($data));

            $lease = $worker->getRequiredLeaseTime();
            if ($lease !== null) {
              $task->setLeaseDuration($lease);
            }

            $t_start = microtime(true);
            $worker->executeTask();
            $t_end = microtime(true);

            $task->archiveTask(
              PhabricatorWorkerArchiveTask::RESULT_SUCCESS,
              (int)(1000000 * ($t_end - $t_start)));
            $this->log("Task {$id} complete! Moved to archive.");
          } catch (Exception $ex) {
            $task->setFailureCount($task->getFailureCount() + 1);
            $task->save();

            $this->log("Task {$id} failed!");
            throw $ex;
          }
        }

        $sleep = 0;
      } else {
        $sleep = min($sleep + 1, 30);
      }

      $this->sleep($sleep);
    } while (true);
  }

  private function getLeaseOwnershipName() {
    static $name = null;
    if ($name === null) {
      $name = getmypid().':'.time().':'.php_uname('n');
    }
    return $name;
  }

}
