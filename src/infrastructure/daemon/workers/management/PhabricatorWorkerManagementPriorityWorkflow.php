<?php

final class PhabricatorWorkerManagementPriorityWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('priority')
      ->setExamples('**priority** __selectors__ --priority __value__')
      ->setSynopsis(
        pht(
          'Change the priority of selected tasks, causing them to execute '.
          'before or after other tasks.'))
      ->setArguments(
        array_merge(
          array(
            array(
              'name' => 'priority',
              'param' => 'int',
              'help' => pht(
                'Set tasks to this priority. Tasks with a smaller priority '.
                'value execute before tasks with a larger priority value.'),
            ),
          ),
          $this->getTaskSelectionArguments()));
  }

  public function execute(PhutilArgumentParser $args) {
    $new_priority = $args->getArg('priority');

    if ($new_priority === null) {
      throw new PhutilArgumentUsageException(
        pht(
          'Select a new priority for selected tasks with "--priority".'));
    }

    $new_priority = (int)$new_priority;
    if ($new_priority <= 0) {
      throw new PhutilArgumentUsageException(
        pht(
          'Priority must be a positive integer.'));
    }

    $tasks = $this->loadTasks($args);

    if (!$tasks) {
      $this->logWarn(
        pht('NO TASKS'),
        pht('No tasks selected to reprioritize.'));

      return 0;
    }

    $priority_count = 0;
    foreach ($tasks as $task) {
      $can_reprioritize = !$task->isArchived();
      if (!$can_reprioritize) {
        $this->logWarn(
          pht('ARCHIVED'),
          pht(
            '%s is already archived, and can not be reprioritized.',
            $this->describeTask($task)));
        continue;
      }


      $old_priority = (int)$task->getPriority();

      if ($old_priority === $new_priority) {
        $this->logWarn(
          pht('UNCHANGED'),
          pht(
            '%s already has priority "%s".',
            $this->describeTask($task),
            $new_priority));
        continue;
      }


      $task
        ->setPriority($new_priority)
        ->save();

      $this->logInfo(
        pht('PRIORITY'),
        pht(
          '%s was reprioritized (from "%d" to "%d").',
          $this->describeTask($task),
          $old_priority,
          $new_priority));

      $priority_count++;
    }

    $this->logOkay(
      pht('DONE'),
      pht('Reprioritized %s task(s).', new PhutilNumber($priority_count)));

    return 0;
  }

}
