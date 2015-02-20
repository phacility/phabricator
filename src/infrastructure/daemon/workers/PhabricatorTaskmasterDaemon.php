<?php

final class PhabricatorTaskmasterDaemon extends PhabricatorDaemon {

  protected function run() {
    $taskmaster_count = PhabricatorEnv::getEnvConfig('phd.start-taskmasters');
    $offset = mt_rand(0, $taskmaster_count - 1);

    do {
      $tasks = id(new PhabricatorWorkerLeaseQuery())
        ->setLimit(1)
        ->execute();

      if ($tasks) {
        foreach ($tasks as $task) {
          $id = $task->getID();
          $class = $task->getTaskClass();

          $this->log("Working on task {$id} ({$class})...");

          $task = $task->executeTask();
          $ex = $task->getExecutionException();
          if ($ex) {
            if ($ex instanceof PhabricatorWorkerPermanentFailureException) {
              $this->log(
                pht(
                  'Task %s failed permanently: %s',
                  $id,
                  $ex->getMessage()));
            } else if ($ex instanceof PhabricatorWorkerYieldException) {
              $this->log(pht('Task %s yielded.', $id));
            } else {
              $this->log("Task {$id} failed!");
              throw new PhutilProxyException(
                "Error while executing task ID {$id} from queue.",
                $ex);
            }
          } else {
            $this->log("Task {$id} complete! Moved to archive.");
          }
        }

        $sleep = 0;
      } else {
        // When there's no work, sleep for as many seconds as there are
        // active taskmasters.

        // On average, this starts tasks added to an empty queue after one
        // second. This keeps responsiveness high even on small instances
        // without much work to do.

        // It also means an empty queue has an average load of one query
        // per second even if there are a very large number of taskmasters
        // launched.

        // The first time we sleep, we add a random offset to try to spread
        // the sleep times out somewhat evenly.
        $sleep = $taskmaster_count + $offset;
        $offset = 0;
      }

      $this->sleep($sleep);
    } while (!$this->shouldExit());
  }

}
