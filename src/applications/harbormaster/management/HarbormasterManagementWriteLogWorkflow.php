<?php

final class HarbormasterManagementWriteLogWorkflow
  extends HarbormasterManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('write-log')
      ->setExamples('**write-log** --target __id__ [__options__]')
      ->setSynopsis(
        pht(
          'Write a new Harbormaster build log. This is primarily intended '.
          'to make development and testing easier.'))
      ->setArguments(
        array(
          array(
            'name' => 'target',
            'param' => 'id',
            'help' => pht('Build Target ID to attach the log to.'),
          ),
          array(
            'name' => 'rate',
            'param' => 'bytes',
            'help' => pht(
              'Limit the rate at which the log is written, to test '.
              'live log streaming.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $target_id = $args->getArg('target');
    if (!$target_id) {
      throw new PhutilArgumentUsageException(
        pht('Choose a build target to attach the log to with "--target".'));
    }

    $target = id(new HarbormasterBuildTargetQuery())
      ->setViewer($viewer)
      ->withIDs(array($target_id))
      ->executeOne();
    if (!$target) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to load build target "%s".',
          $target_id));
    }

    $log = HarbormasterBuildLog::initializeNewBuildLog($target);
    $log->openBuildLog();

    echo tsprintf(
      "%s\n\n        __%s__\n\n",
      pht('Opened a new build log:'),
      PhabricatorEnv::getURI($log->getURI()));

    echo tsprintf(
      "%s\n",
      pht('Reading log content from stdin...'));

    $content = file_get_contents('php://stdin');

    $rate = $args->getArg('rate');
    if ($rate) {
      if ($rate <= 0) {
        throw new Exception(
          pht(
            'Write rate must be more than 0 bytes/sec.'));
      }

      echo tsprintf(
        "%s\n",
        pht('Writing log, slowly...'));

      $offset = 0;
      $total = strlen($content);
      $pieces = str_split($content, $rate);

      $bar = id(new PhutilConsoleProgressBar())
        ->setTotal($total);

      foreach ($pieces as $piece) {
        $log->append($piece);
        $bar->update(strlen($piece));
        sleep(1);
      }

      $bar->done();

    } else {
      $log->append($content);
    }

    echo tsprintf(
      "%s\n",
      pht('Write completed. Closing log...'));

    PhabricatorWorker::setRunAllTasksInProcess(true);

    $log->closeBuildLog();

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
