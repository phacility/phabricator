<?php

final class PhabricatorWorkerManagementRetryWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('retry')
      ->setExamples('**retry** --id __id__')
      ->setSynopsis(
        pht(
          'Retry selected tasks which previously failed permanently or '.
          'were cancelled. Only archived, unsuccessful tasks can be '.
          'retried.'))
      ->setArguments($this->getTaskSelectionArguments());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $tasks = $this->loadTasks($args);

    foreach ($tasks as $task) {
      if (!$task->isArchived()) {
        $console->writeOut(
          "**<bg:yellow> %s </bg>** %s\n",
          pht('ACTIVE'),
          pht(
            '%s is already in the active task queue.',
            $this->describeTask($task)));
        continue;
      }

      $result_success = PhabricatorWorkerArchiveTask::RESULT_SUCCESS;
      if ($task->getResult() == $result_success) {
        $console->writeOut(
          "**<bg:yellow> %s </bg>** %s\n",
          pht('SUCCEEDED'),
          pht(
            '%s has already succeeded, and can not be retried.',
            $this->describeTask($task)));
        continue;
      }

      $task->unarchiveTask();

      $console->writeOut(
        "**<bg:green> %s </bg>** %s\n",
        pht('QUEUED'),
        pht(
          '%s was queued for retry.',
          $this->describeTask($task)));
    }

    return 0;
  }

}
