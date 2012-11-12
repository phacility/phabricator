<?php

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

          $task = $task->executeTask();
          $ex = $task->getExecutionException();
          if ($ex) {
            $this->log("Task {$id} failed!");
            throw $ex;
          } else {
            $this->log("Task {$id} complete! Moved to archive.");
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
