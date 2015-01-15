<?php

final class PhabricatorWorkerManagementFreeWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('free')
      ->setExamples('**free** --id __id__')
      ->setSynopsis(
        pht(
          'Free leases on selected tasks. If the daemon holding the lease is '.
          'still working on the task, this may cause the task to execute '.
          'twice.'))
      ->setArguments($this->getTaskSelectionArguments());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $tasks = $this->loadTasks($args);

    foreach ($tasks as $task) {
      if ($task->isArchived()) {
        $console->writeOut(
          "**<bg:yellow> %s </bg>** %s\n",
          pht('ARCHIVED'),
          pht(
            '%s is archived; archived tasks do not have leases.',
            $this->describeTask($task)));
        continue;
      }

      if ($task->getLeaseOwner() === null) {
        $console->writeOut(
          "**<bg:yellow> %s </bg>** %s\n",
          pht('FREE'),
          pht(
            '%s has no active lease.',
            $this->describeTask($task)));
        continue;
      }

      $task->setLeaseOwner(null);
      $task->setLeaseExpires(PhabricatorTime::getNow());
      $task->save();

      $console->writeOut(
        "**<bg:green> %s </bg>** %s\n",
        pht('LEASE FREED'),
        pht(
          '%s was freed from its lease.',
          $this->describeTask($task)));
    }

    return 0;
  }

}
