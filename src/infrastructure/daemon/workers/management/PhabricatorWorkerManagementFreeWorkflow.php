<?php

final class PhabricatorWorkerManagementFreeWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('free')
      ->setExamples('**free** __selectors__')
      ->setSynopsis(
        pht(
          'Free leases on selected tasks. If the daemon holding the lease is '.
          'still working on the task, this may cause the task to execute '.
          'twice.'))
      ->setArguments($this->getTaskSelectionArguments());
  }

  public function execute(PhutilArgumentParser $args) {
    $tasks = $this->loadTasks($args);

    if (!$tasks) {
      $this->logWarn(
        pht('NO TASKS'),
        pht('No tasks selected to free leases on.'));

      return 0;
    }

    $free_count = 0;
    foreach ($tasks as $task) {
      if ($task->isArchived()) {
        $this->logWarn(
          pht('ARCHIVED'),
          pht(
            '%s is archived; archived tasks do not have leases.',
            $this->describeTask($task)));
        continue;
      }

      if ($task->getLeaseOwner() === null) {
        $this->logWarn(
          pht('FREE'),
          pht(
            '%s has no active lease.',
            $this->describeTask($task)));
        continue;
      }

      $task
        ->setLeaseOwner(null)
        ->setLeaseExpires(PhabricatorTime::getNow())
        ->save();

      $this->logInfo(
        pht('LEASE FREED'),
        pht(
          '%s was freed from its lease.',
          $this->describeTask($task)));

      $free_count++;
    }

    $this->logOkay(
      pht('DONE'),
      pht('Freed %s task lease(s).', new PhutilNumber($free_count)));

    return 0;
  }

}
