<?php

final class PhabricatorWorkerManagementExecuteWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('execute')
      ->setExamples('**execute** --id __id__')
      ->setSynopsis(
        pht(
          'Execute a task explicitly. This command ignores leases, is '.
          'dangerous, and may cause work to be performed twice.'))
      ->setArguments(
        array_merge(
          array(
            array(
              'name' => 'retry',
              'help' => pht('Retry archived tasks.'),
            ),
            array(
              'name' => 'repeat',
              'help' => pht('Repeat archived, successful tasks.'),
            ),
          ),
          $this->getTaskSelectionArguments()));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $tasks = $this->loadTasks($args);

    $is_retry = $args->getArg('retry');
    $is_repeat = $args->getArg('repeat');

    foreach ($tasks as $task) {
      $can_execute = !$task->isArchived();
      if (!$can_execute) {
        if (!$is_retry) {
          $console->writeOut(
            "**<bg:yellow> %s </bg>** %s\n",
            pht('ARCHIVED'),
            pht(
              '%s is already archived, and will not be executed. '.
              'Use "--retry" to execute archived tasks.',
              $this->describeTask($task)));
          continue;
        }

        $result_success = PhabricatorWorkerArchiveTask::RESULT_SUCCESS;
        if ($task->getResult() == $result_success) {
          if (!$is_repeat) {
            $console->writeOut(
              "**<bg:yellow> %s </bg>** %s\n",
              pht('SUCCEEDED'),
              pht(
                '%s has already succeeded, and will not be retried. '.
                'Use "--repeat" to repeat successful tasks.',
                $this->describeTask($task)));
            continue;
          }
        }

        echo tsprintf(
          "**<bg:yellow> %s </bg>** %s\n",
          pht('ARCHIVED'),
          pht(
            'Unarchiving %s.',
            $this->describeTask($task)));

        $task = $task->unarchiveTask();
      }

      // NOTE: This ignores leases, maybe it should respect them without
      // a parameter like --force?

      $task->setLeaseOwner(null);
      $task->setLeaseExpires(PhabricatorTime::getNow());
      $task->save();

      $task_data = id(new PhabricatorWorkerTaskData())->loadOneWhere(
        'id = %d',
        $task->getDataID());
      $task->setData($task_data->getData());

      echo tsprintf(
        "%s\n",
        pht(
          'Executing task %d (%s)...',
          $task->getID(),
          $task->getTaskClass()));

      $task = $task->executeTask();
      $ex = $task->getExecutionException();

      if ($ex) {
        throw $ex;
      }
    }

    return 0;
  }

}
