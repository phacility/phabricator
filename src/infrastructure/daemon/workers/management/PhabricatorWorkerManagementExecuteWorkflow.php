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
      ->setArguments($this->getTaskSelectionArguments());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $tasks = $this->loadTasks($args);

    foreach ($tasks as $task) {
      $can_execute = !$task->isArchived();
      if (!$can_execute) {
        $console->writeOut(
          "**<bg:yellow> %s </bg>** %s\n",
          pht('ARCHIVED'),
          pht(
            '%s is already archived, and can not be executed.',
            $this->describeTask($task)));
        continue;
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
