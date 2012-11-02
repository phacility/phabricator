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

final class PhabricatorWorkerTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testLeaseTask() {
    // Leasing should work.

    $task = $this->scheduleTask();

    $this->expectNextLease($task);
  }

  public function testMultipleLease() {
    // We should not be able to lease a task multiple times.

    $task = $this->scheduleTask();

    $this->expectNextLease($task);
    $this->expectNextLease(null);
  }

  public function testOldestFirst() {
    // Older tasks should lease first, all else being equal.

    $task1 = $this->scheduleTask();
    $task2 = $this->scheduleTask();

    $this->expectNextLease($task1);
    $this->expectNextLease($task2);
  }

  public function testNewBeforeLeased() {
    // Tasks not previously leased should lease before previously leased tasks.

    $task1 = $this->scheduleTask();
    $task2 = $this->scheduleTask();

    $task1->setLeaseOwner('test');
    $task1->setLeaseExpires(time() - 100000);
    $task1->forceSaveWithoutLease();

    $this->expectNextLease($task2);
    $this->expectNextLease($task1);
  }


  public function testExecuteTask() {
    $task = $this->scheduleAndExecuteTask();

    $this->assertEqual(true, $task->isArchived());
    $this->assertEqual(
      PhabricatorWorkerArchiveTask::RESULT_SUCCESS,
      $task->getResult());
  }

  public function testPermanentTaskFailure() {
    $task = $this->scheduleAndExecuteTask(
      array(
        'doWork' => 'fail-permanent',
      ));

    $this->assertEqual(true, $task->isArchived());
    $this->assertEqual(
      PhabricatorWorkerArchiveTask::RESULT_FAILURE,
      $task->getResult());
  }

  public function testTemporaryTaskFailure() {
    $task = $this->scheduleAndExecuteTask(
      array(
        'doWork' => 'fail-temporary',
      ));

    $this->assertEqual(false, $task->isArchived());
    $this->assertEqual(
      true,
      ($task->getExecutionException() instanceof Exception));
  }

  public function testTooManyTaskFailures() {
    // Expect temporary failures, then a permanent failure.
    $task = $this->scheduleAndExecuteTask(
      array(
        'doWork'                => 'fail-temporary',
        'getMaximumRetryCount'  => 3,
        'getWaitBeforeRetry'    => -60,
      ));

    // Temporary...
    $this->assertEqual(false, $task->isArchived());
    $this->assertEqual(
      true,
      ($task->getExecutionException() instanceof Exception));
    $this->assertEqual(1, $task->getFailureCount());

    // Temporary...
    $task = $this->expectNextLease($task);
    $task = $task->executeTask();
    $this->assertEqual(false, $task->isArchived());
    $this->assertEqual(
      true,
      ($task->getExecutionException() instanceof Exception));
    $this->assertEqual(2, $task->getFailureCount());

    // Temporary...
    $task = $this->expectNextLease($task);
    $task = $task->executeTask();
    $this->assertEqual(false, $task->isArchived());
    $this->assertEqual(
      true,
      ($task->getExecutionException() instanceof Exception));
    $this->assertEqual(3, $task->getFailureCount());

    // Temporary...
    $task = $this->expectNextLease($task);
    $task = $task->executeTask();
    $this->assertEqual(false, $task->isArchived());
    $this->assertEqual(
      true,
      ($task->getExecutionException() instanceof Exception));
    $this->assertEqual(4, $task->getFailureCount());

    // Permanent.
    $task = $this->expectNextLease($task);
    $task = $task->executeTask();
    $this->assertEqual(true, $task->isArchived());
    $this->assertEqual(
      PhabricatorWorkerArchiveTask::RESULT_FAILURE,
      $task->getResult());
  }

  public function testWaitBeforeRetry() {
    $task = $this->scheduleTask(
      array(
        'doWork'                => 'fail-temporary',
        'getWaitBeforeRetry'    => 1000000,
      ));

    $this->expectNextLease($task)->executeTask();
    $this->expectNextLease(null);
  }

  public function testRequiredLeaseTime() {
    $task = $this->scheduleAndExecuteTask(
      array(
        'getRequiredLeaseTime'    => 1000000,
      ));

    $this->assertEqual(true, ($task->getLeaseExpires() - time()) > 1000);
  }

  private function expectNextLease($task) {
    $leased = id(new PhabricatorWorkerLeaseQuery())
      ->setLimit(1)
      ->execute();

    if ($task === null) {
      $this->assertEqual(0, count($leased));
      return null;
    } else {
      $this->assertEqual(1, count($leased));
      $this->assertEqual(
        (int)head($leased)->getID(),
        (int)$task->getID());
      return head($leased);
    }
  }

  private function scheduleAndExecuteTask(array $data = array()) {
    $task = $this->scheduleTask($data);
    $task = $this->expectNextLease($task);
    $task = $task->executeTask();
    return $task;
  }

  private function scheduleTask(array $data = array()) {
    return PhabricatorWorker::scheduleTask('PhabricatorTestWorker', $data);
  }

}
