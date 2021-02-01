<?php

final class PhabricatorWorkerManagementRetryWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('retry')
      ->setExamples('**retry** __selectors__')
      ->setSynopsis(
        pht(
          'Retry selected tasks which previously failed permanently or '.
          'were cancelled. Only archived tasks can be retried.'))
      ->setArguments(
        array_merge(
          array(
            array(
              'name' => 'repeat',
              'help' => pht(
                'Repeat tasks which already completed successfully.'),
            ),
          ),
          $this->getTaskSelectionArguments()));
  }

  public function execute(PhutilArgumentParser $args) {
    $is_repeat = $args->getArg('repeat');

    $tasks = $this->loadTasks($args);
    if (!$tasks) {
      $this->logWarn(
        pht('NO TASKS'),
        pht('No tasks selected to retry.'));

      return 0;
    }

    $retry_count = 0;
    foreach ($tasks as $task) {
      if (!$task->isArchived()) {
        $this->logWarn(
          pht('ACTIVE'),
          pht(
            '%s is already in the active task queue.',
            $this->describeTask($task)));
        continue;
      }

      $result_success = PhabricatorWorkerArchiveTask::RESULT_SUCCESS;
      if ($task->getResult() == $result_success) {
        if (!$is_repeat) {
          $this->logWarn(
            pht('SUCCEEDED'),
            pht(
              '%s has already succeeded, and will not be repeated. '.
              'Use "--repeat" to repeat successful tasks.',
              $this->describeTask($task)));
          continue;
        }
      }

      $task->unarchiveTask();

      $this->logInfo(
        pht('QUEUED'),
        pht(
          '%s was queued for retry.',
          $this->describeTask($task)));

      $retry_count++;
    }

    $this->logOkay(
      pht('DONE'),
      pht('Queued %s task(s) for retry.', new PhutilNumber($retry_count)));

    return 0;
  }

}
