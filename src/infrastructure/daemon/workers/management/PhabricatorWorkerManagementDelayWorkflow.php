<?php

final class PhabricatorWorkerManagementDelayWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('delay')
      ->setExamples(
        implode(
          "\n",
          array(
            '**delay** __selectors__ --until __date__',
            '**delay** __selectors__ --until __YYYY-MM-DD__',
            '**delay** __selectors__ --until "6 hours"',
            '**delay** __selectors__ --until now',
          )))
      ->setSynopsis(
        pht(
          'Delay execution of selected tasks until the specified time.'))
      ->setArguments(
        array_merge(
          array(
            array(
              'name' => 'until',
              'param' => 'date',
              'help' => pht(
                'Select the date or time to delay the selected tasks until.'),
            ),
          ),
          $this->getTaskSelectionArguments()));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $until = $args->getArg('until');
    $until = $this->parseTimeArgument($until);

    if ($until === null) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify how long to delay tasks for with "--until".'));
    }

    $tasks = $this->loadTasks($args);

    if (!$tasks) {
      $this->logWarn(
        pht('NO TASKS'),
        pht('No tasks selected to delay.'));

      return 0;
    }

    $delay_count = 0;
    foreach ($tasks as $task) {
      if ($task->isArchived()) {
        $this->logWarn(
          pht('ARCHIVED'),
          pht(
            '%s is already archived, and can not be delayed.',
            $this->describeTask($task)));
        continue;
      }

      if ($task->getLeaseOwner()) {
        $this->logWarn(
          pht('LEASED'),
          pht(
            '% is already leased, and can not be delayed.',
            $this->describeTask($task)));
        continue;
      }

      $task
        ->setLeaseExpires($until)
        ->save();

      $this->logInfo(
        pht('DELAY'),
        pht(
          '%s was delayed until "%s".',
          $this->describeTask($task),
          phabricator_datetime($until, $viewer)));

      $delay_count++;
    }

    $this->logOkay(
      pht('DONE'),
      pht('Delayed %s task(s).', new PhutilNumber($delay_count)));

    return 0;
  }

}
