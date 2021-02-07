<?php

final class PhabricatorWorkerManagementCancelWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('cancel')
      ->setExamples('**cancel** __selectors__')
      ->setSynopsis(
        pht(
          'Cancel selected tasks. The work these tasks represent will never '.
          'be performed.'))
      ->setArguments($this->getTaskSelectionArguments());
  }

  public function execute(PhutilArgumentParser $args) {
    $tasks = $this->loadTasks($args);

    if (!$tasks) {
      $this->logWarn(
        pht('NO TASKS'),
        pht('No tasks selected to cancel.'));

      return 0;
    }

    $cancel_count = 0;
    foreach ($tasks as $task) {
      $can_cancel = !$task->isArchived();
      if (!$can_cancel) {
        $this->logWarn(
          pht('ARCHIVED'),
          pht(
            '%s is already archived, and can not be cancelled.',
            $this->describeTask($task)));
        continue;
      }

      // Forcibly break the lease if one exists, so we can archive the
      // task.
      $task
        ->setLeaseOwner(null)
        ->setLeaseExpires(PhabricatorTime::getNow());

      $task->archiveTask(PhabricatorWorkerArchiveTask::RESULT_CANCELLED, 0);

      $this->logInfo(
        pht('CANCELLED'),
        pht(
          '%s was cancelled.',
          $this->describeTask($task)));

      $cancel_count++;
    }

    $this->logOkay(
      pht('DONE'),
      pht('Cancelled %s task(s).', new PhutilNumber($cancel_count)));

    return 0;
  }

}
