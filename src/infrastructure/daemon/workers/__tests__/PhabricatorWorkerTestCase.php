<?php

final class PhabricatorWorkerTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  protected function willRunOneTest($test) {
    parent::willRunOneTest($test);

    // Before we run these test cases, clear the queue. After D20412, we may
    // have queued tasks from migrations.
    $task_table = new PhabricatorWorkerActiveTask();
    $conn = $task_table->establishConnection('w');

    queryfx(
      $conn,
      'TRUNCATE %R',
      $task_table);
  }

  public function testLeaseTask() {
    $task = $this->scheduleTask();
    $this->expectNextLease($task, pht('Leasing should work.'));
  }

  public function testMultipleLease() {
    $task = $this->scheduleTask();

    $this->expectNextLease($task);
    $this->expectNextLease(
      null,
      pht('We should not be able to lease a task multiple times.'));
  }

  public function testOldestFirst() {
    $task1 = $this->scheduleTask();
    $task2 = $this->scheduleTask();

    $this->expectNextLease(
      $task1,
      pht('Older tasks should lease first, all else being equal.'));
    $this->expectNextLease($task2);
  }

  public function testNewBeforeLeased() {
    $task1 = $this->scheduleTask();
    $task2 = $this->scheduleTask();

    $task1->setLeaseOwner('test');
    $task1->setLeaseExpires(time() - 100000);
    $task1->forceSaveWithoutLease();

    $this->expectNextLease(
      $task2,
      pht(
        'Tasks not previously leased should lease before previously '.
        'leased tasks.'));
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

    $this->assertFalse($task->isArchived());
    $this->assertTrue($task->getExecutionException() instanceof Exception);
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
    $this->assertFalse($task->isArchived());
    $this->assertTrue($task->getExecutionException() instanceof Exception);
    $this->assertEqual(1, $task->getFailureCount());

    // Temporary...
    $task = $this->expectNextLease($task);
    $task = $task->executeTask();
    $this->assertFalse($task->isArchived());
    $this->assertTrue($task->getExecutionException() instanceof Exception);
    $this->assertEqual(2, $task->getFailureCount());

    // Temporary...
    $task = $this->expectNextLease($task);
    $task = $task->executeTask();
    $this->assertFalse($task->isArchived());
    $this->assertTrue($task->getExecutionException() instanceof Exception);
    $this->assertEqual(3, $task->getFailureCount());

    // Temporary...
    $task = $this->expectNextLease($task);
    $task = $task->executeTask();
    $this->assertFalse($task->isArchived());
    $this->assertTrue($task->getExecutionException() instanceof Exception);
    $this->assertEqual(4, $task->getFailureCount());

    // Permanent.
    $task = $this->expectNextLease($task);
    $task = $task->executeTask();
    $this->assertTrue($task->isArchived());
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
        'getRequiredLeaseTime'     => 1000000,
      ));

    $this->assertTrue(($task->getLeaseExpires() - time()) > 1000);
  }

  public function testLeasedIsOldestFirst() {
    $task1 = $this->scheduleTask();
    $task2 = $this->scheduleTask();

    $task1->setLeaseOwner('test');
    $task1->setLeaseExpires(time() - 100000);
    $task1->forceSaveWithoutLease();

    $task2->setLeaseOwner('test');
    $task2->setLeaseExpires(time() - 200000);
    $task2->forceSaveWithoutLease();

    $this->expectNextLease(
      $task2,
      pht(
        'Tasks which expired earlier should lease first, '.
        'all else being equal.'));
    $this->expectNextLease($task1);
  }

  public function testLeasedIsLowestPriority() {
    $task1 = $this->scheduleTask(array(), 2);
    $task2 = $this->scheduleTask(array(), 2);
    $task3 = $this->scheduleTask(array(), 1);

    $this->expectNextLease(
      $task3,
      pht('Tasks with a lower priority should be scheduled first.'));
    $this->expectNextLease(
      $task1,
      pht('Tasks with the same priority should be FIFO.'));
    $this->expectNextLease($task2);
  }

  private function expectNextLease($task, $message = null) {
    $leased = id(new PhabricatorWorkerLeaseQuery())
      ->setLimit(1)
      ->execute();

    if ($task === null) {
      $this->assertEqual(0, count($leased), $message);
      return null;
    } else {
      $this->assertEqual(1, count($leased), $message);
      $this->assertEqual(
        (int)head($leased)->getID(),
        (int)$task->getID(),
        $message);
      return head($leased);
    }
  }

  private function scheduleAndExecuteTask(
    array $data = array(),
    $priority = null) {

    $task = $this->scheduleTask($data, $priority);
    $task = $this->expectNextLease($task);
    $task = $task->executeTask();
    return $task;
  }

  private function scheduleTask(array $data = array(), $priority = null) {
    return PhabricatorWorker::scheduleTask(
      'PhabricatorTestWorker',
      $data,
      array('priority' => $priority));
  }

}
