<?php

final class PhabricatorTaskmasterDaemon extends PhabricatorDaemon {

  protected function run() {
    do {
      $tasks = id(new PhabricatorWorkerLeaseQuery())
        ->setLimit(1)
        ->execute();

      if ($tasks) {
        $this->willBeginWork();

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
        // When there's no work, sleep for one second. The pool will
        // autoscale down if we're continuously idle for an extended period
        // of time.
        $this->willBeginIdle();
        $sleep = 1;
      }

      $this->sleep($sleep);
    } while (!$this->shouldExit());
  }

}
