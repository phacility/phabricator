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

/**
 * @task config   Configuring Retries and Failures
 *
 * @group worker
 */
abstract class PhabricatorWorker {

  private $data;


/* -(  Configuring Retries and Failures  )----------------------------------- */


  /**
   * Return the number of seconds this worker needs hold a lease on the task for
   * while it performs work. For most tasks you can leave this at `null`, which
   * will give you a short default lease (currently 60 seconds).
   *
   * For tasks which may take a very long time to complete, you should return
   * an upper bound on the amount of time the task may require.
   *
   * @return int|null  Number of seconds this task needs to remain leased for,
   *                   or null for a default (currently 60 second) lease.
   *
   * @task config
   */
  public function getRequiredLeaseTime() {
    return null;
  }


  /**
   * Return the maximum number of times this task may be retried before it
   * is considered permanently failed. By default, tasks retry indefinitely. You
   * can throw a @{class:PhabricatorWorkerPermanentFailureException} to cause an
   * immediate permanent failure.
   *
   * @return int|null   Number of times the task will retry before permanent
   *                    failure. Return `null` to retry indefinitely.
   *
   * @task config
   */
  public function getMaximumRetryCount() {
    return null;
  }


  /**
   * Return the number of seconds a task should wait after a failure before
   * retrying. For most tasks you can leave this at `null`, which will give you
   * a short default retry period (currently 60 seconds).
   *
   * @param   PhabricatorWorkerTask   The task itself. This object is probably
   *                                  useful mostly to examine the failure
   *                                  count if you want to implement staggered
   *                                  retries, or to examine the execution
   *                                  exception if you want to react to
   *                                  different failures in different ways.
   * @param   Exception               The exception which caused the failure.
   * @return  int|null                Number of seconds to wait between retries,
   *                                  or null for a default retry period
   *                                  (currently 60 seconds).
   *
   * @task config
   */
  public function getWaitBeforeRetry(PhabricatorWorkerTask $task) {
    return null;
  }

  abstract protected function doWork();


  final public function __construct($data) {
    $this->data = $data;
  }

  final protected function getTaskData() {
    return $this->data;
  }

  final public function executeTask() {
    $this->doWork();
  }

  final public static function scheduleTask($task_class, $data) {
    return id(new PhabricatorWorkerActiveTask())
      ->setTaskClass($task_class)
      ->setData($data)
      ->save();
  }

}
