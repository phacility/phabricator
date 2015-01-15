<?php

final class PhabricatorWorkerManagementCancelWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('cancel')
      ->setExamples('**cancel** --id __id__')
      ->setSynopsis(
        pht(
          'Cancel selected tasks. The work these tasks represent will never '.
          'be performed.'))
      ->setArguments($this->getTaskSelectionArguments());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $tasks = $this->loadTasks($args);

    foreach ($tasks as $task) {
      $can_cancel = !$task->isArchived();
      if (!$can_cancel) {
        $console->writeOut(
          "**<bg:yellow> %s </bg>** %s\n",
          pht('ARCHIVED'),
          pht(
            '%s is already archived, and can not be cancelled.',
            $this->describeTask($task)));
        continue;
      }

      // Forcibly break the lease if one exists, so we can archive the
      // task.
      $task->setLeaseOwner(null);
      $task->setLeaseExpires(PhabricatorTime::getNow());
      $task->archiveTask(
        PhabricatorWorkerArchiveTask::RESULT_CANCELLED,
        0);

      $console->writeOut(
        "**<bg:green> %s </bg>** %s\n",
        pht('CANCELLED'),
        pht(
          '%s was cancelled.',
          $this->describeTask($task)));
    }

    return 0;
  }

}
