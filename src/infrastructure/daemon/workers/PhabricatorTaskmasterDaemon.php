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
    $sleep = 0;
    do {
      $tasks = id(new PhabricatorWorkerLeaseQuery())
        ->setLimit(1)
        ->execute();

      if ($tasks) {
        foreach ($tasks as $task) {
          $id = $task->getID();
          $class = $task->getTaskClass();

          $this->log("Working on task {$id} ({$class})...");

          // TODO: We should detect if we acquired a task with an expired lease
          // and log about it / bump up failure count.

          // TODO: We should detect if we acquired a task with an excessive
          // failure count and fail it permanently.

          $data = $task->getData();
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

}
