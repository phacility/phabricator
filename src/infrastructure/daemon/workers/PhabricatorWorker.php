<?php

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


  /**
   * Wait for tasks to complete. If tasks are not leased by other workers, they
   * will be executed in this process while waiting.
   *
   * @param list<int>   List of queued task IDs to wait for.
   * @return void
   */
  final public static function waitForTasks(array $task_ids) {
    $task_table = new PhabricatorWorkerActiveTask();

    $waiting = array_fuse($task_ids);
    while ($waiting) {
      $conn_w = $task_table->establishConnection('w');

      // Check if any of the tasks we're waiting on are still queued. If they
      // are not, we're done waiting.
      $row = queryfx_one(
        $conn_w,
        'SELECT COUNT(*) N FROM %T WHERE id IN (%Ld)',
        $task_table->getTableName(),
        $waiting);
      if (!$row['N']) {
        // Nothing is queued anymore. Stop waiting.
        break;
      }

      $tasks = id(new PhabricatorWorkerLeaseQuery())
        ->withIDs($waiting)
        ->setLimit(1)
        ->execute();

      if (!$tasks) {
        // We were not successful in leasing anything. Sleep for a bit and
        // see if we have better luck later.
        sleep(1);
        continue;
      }

      $task = head($tasks)->executeTask();

      $ex = $task->getExecutionException();
      if ($ex) {
        throw $ex;
      }
    }

    $tasks = id(new PhabricatorWorkerArchiveTask())->loadAllWhere(
      'id IN (%Ld)',
      $task_ids);

    foreach ($tasks as $task) {
      if ($task->getResult() != PhabricatorWorkerArchiveTask::RESULT_SUCCESS) {
        throw new Exception("Task ".$task->getID()." failed!");
      }
    }
  }

  public function renderForDisplay() {
    $data = PhutilReadableSerializer::printableValue($this->data);
    return phutil_tag('pre', array(), $data);
  }

}
