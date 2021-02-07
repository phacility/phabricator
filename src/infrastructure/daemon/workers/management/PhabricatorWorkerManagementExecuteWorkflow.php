<?php

final class PhabricatorWorkerManagementExecuteWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('execute')
      ->setExamples('**execute** __selectors__')
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
    $is_retry = $args->getArg('retry');
    $is_repeat = $args->getArg('repeat');

    $tasks = $this->loadTasks($args);
    if (!$tasks) {
      $this->logWarn(
        pht('NO TASKS'),
        pht('No tasks selected to execute.'));

      return 0;
    }

    $execute_count = 0;
    foreach ($tasks as $task) {
      $can_execute = !$task->isArchived();
      if (!$can_execute) {
        if (!$is_retry) {
          $this->logWarn(
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
            $this->logWarn(
              pht('SUCCEEDED'),
              pht(
                '%s has already succeeded, and will not be retried. '.
                'Use "--repeat" to repeat successful tasks.',
                $this->describeTask($task)));
            continue;
          }
        }

        $this->logInfo(
          pht('UNARCHIVING'),
          pht(
            'Unarchiving %s.',
            $this->describeTask($task)));

        $task = $task->unarchiveTask();
      }

      // NOTE: This ignores leases, maybe it should respect them without
      // a parameter like --force?

      $task
        ->setLeaseOwner(null)
        ->setLeaseExpires(PhabricatorTime::getNow())
        ->save();

      $task_data = id(new PhabricatorWorkerTaskData())->loadOneWhere(
        'id = %d',
        $task->getDataID());
      $task->setData($task_data->getData());

      $this->logInfo(
        pht('EXECUTE'),
        pht(
          'Executing %s...',
          $this->describeTask($task)));

      $task = $task->executeTask();

      $ex = $task->getExecutionException();
      if ($ex) {
        throw $ex;
      }

      $execute_count++;
    }

    $this->logOkay(
      pht('DONE'),
      pht('Executed %s task(s).', new PhutilNumber($execute_count)));

    return 0;
  }

}
