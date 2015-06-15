<?php

/**
 * @task config   Configuring Retries and Failures
 */
abstract class PhabricatorWorker extends Phobject {

  private $data;
  private static $runAllTasksInProcess = false;
  private $queuedTasks = array();

  // NOTE: Lower priority numbers execute first. The priority numbers have to
  // have the same ordering that IDs do (lowest first) so MySQL can use a
  // multipart key across both of them efficiently.

  const PRIORITY_ALERTS  = 1000;
  const PRIORITY_DEFAULT = 2000;
  const PRIORITY_BULK    = 3000;
  const PRIORITY_IMPORT  = 4000;


/* -(  Configuring Retries and Failures  )----------------------------------- */


  /**
   * Return the number of seconds this worker needs hold a lease on the task for
   * while it performs work. For most tasks you can leave this at `null`, which
   * will give you a default lease (currently 2 hours).
   *
   * For tasks which may take a very long time to complete, you should return
   * an upper bound on the amount of time the task may require.
   *
   * @return int|null  Number of seconds this task needs to remain leased for,
   *                   or null for a default lease.
   *
   * @task config
   */
  public function getRequiredLeaseTime() {
    return null;
  }


  /**
   * Return the maximum number of times this task may be retried before it is
   * considered permanently failed. By default, tasks retry indefinitely. You
   * can throw a @{class:PhabricatorWorkerPermanentFailureException} to cause an
   * immediate permanent failure.
   *
   * @return int|null  Number of times the task will retry before permanent
   *                   failure. Return `null` to retry indefinitely.
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
   * @param  PhabricatorWorkerTask  The task itself. This object is probably
   *                                useful mostly to examine the failure count
   *                                if you want to implement staggered retries,
   *                                or to examine the execution exception if
   *                                you want to react to different failures in
   *                                different ways.
   * @return int|null               Number of seconds to wait between retries,
   *                                or null for a default retry period
   *                                (currently 60 seconds).
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

  final public static function scheduleTask(
    $task_class,
    $data,
    $options = array()) {

    $priority = idx($options, 'priority');
    if ($priority === null) {
      $priority = self::PRIORITY_DEFAULT;
    }
    $object_phid = idx($options, 'objectPHID');

    $task = id(new PhabricatorWorkerActiveTask())
      ->setTaskClass($task_class)
      ->setData($data)
      ->setPriority($priority)
      ->setObjectPHID($object_phid);

    if (self::$runAllTasksInProcess) {
      // Do the work in-process.
      $worker = newv($task_class, array($data));

      while (true) {
        try {
          $worker->doWork();
          foreach ($worker->getQueuedTasks() as $queued_task) {
            list($queued_class, $queued_data, $queued_priority) = $queued_task;
            $queued_options = array('priority' => $queued_priority);
            self::scheduleTask($queued_class, $queued_data, $queued_options);
          }
          break;
        } catch (PhabricatorWorkerYieldException $ex) {
          phlog(
            pht(
              'In-process task "%s" yielded for %s seconds, sleeping...',
              $task_class,
              $ex->getDuration()));
          sleep($ex->getDuration());
        }
      }

      // Now, save a task row and immediately archive it so we can return an
      // object with a valid ID.
      $task->openTransaction();
        $task->save();
        $archived = $task->archiveTask(
          PhabricatorWorkerArchiveTask::RESULT_SUCCESS,
          0);
      $task->saveTransaction();

      return $archived;
    } else {
      $task->save();
      return $task;
    }
  }


  /**
   * Wait for tasks to complete. If tasks are not leased by other workers, they
   * will be executed in this process while waiting.
   *
   * @param list<int>   List of queued task IDs to wait for.
   * @return void
   */
  final public static function waitForTasks(array $task_ids) {
    if (!$task_ids) {
      return;
    }

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

    $tasks = id(new PhabricatorWorkerArchiveTaskQuery())
      ->withIDs($task_ids);

    foreach ($tasks as $task) {
      if ($task->getResult() != PhabricatorWorkerArchiveTask::RESULT_SUCCESS) {
        throw new Exception(pht('Task %d failed!', $task->getID()));
      }
    }
  }

  public function renderForDisplay(PhabricatorUser $viewer) {
    $data = PhutilReadableSerializer::printableValue($this->data);
    return phutil_tag('pre', array(), $data);
  }

  /**
   * Set this flag to execute scheduled tasks synchronously, in the same
   * process. This is useful for debugging, and otherwise dramatically worse
   * in every way imaginable.
   */
  public static function setRunAllTasksInProcess($all) {
    self::$runAllTasksInProcess = $all;
  }

  final protected function log($pattern /* , ... */) {
    $console = PhutilConsole::getConsole();
    $argv = func_get_args();
    call_user_func_array(array($console, 'writeLog'), $argv);
    return $this;
  }


  /**
   * Queue a task to be executed after this one succeeds.
   *
   * The followup task will be queued only if this task completes cleanly.
   *
   * @param string    Task class to queue.
   * @param array     Data for the followup task.
   * @param int|null  Priority for the followup task.
   * @return this
   */
  final protected function queueTask($class, array $data, $priority = null) {
    $this->queuedTasks[] = array($class, $data, $priority);
    return $this;
  }


  /**
   * Get tasks queued as followups by @{method:queueTask}.
   *
   * @return list<tuple<string, wild, int|null>> Queued task specifications.
   */
  final public function getQueuedTasks() {
    return $this->queuedTasks;
  }

}
