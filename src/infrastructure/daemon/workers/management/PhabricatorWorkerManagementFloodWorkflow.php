<?php

final class PhabricatorWorkerManagementFloodWorkflow
  extends PhabricatorWorkerManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('flood')
      ->setExamples('**flood**')
      ->setSynopsis(
        pht(
          'Flood the queue with test tasks. This command is intended for '.
          'use when developing and debugging Phabricator.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $console->writeOut(
      "%s\n",
      pht('Adding many test tasks to worker queue. Use ^C to exit.'));

    $n = 0;
    while (true) {
      PhabricatorWorker::scheduleTask(
        'PhabricatorTestWorker',
        array());

      if (($n++ % 100) === 0) {
        $console->writeOut('.');
      }
    }
  }

}
