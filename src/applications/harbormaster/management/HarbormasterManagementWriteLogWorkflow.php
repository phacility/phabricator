<?php

final class HarbormasterManagementWriteLogWorkflow
  extends HarbormasterManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('write-log')
      ->setExamples('**write-log** --target __id__ [__options__]')
      ->setSynopsis(pht('Write a new Harbormaster build log.'))
      ->setArguments(
        array(
          array(
            'name' => 'target',
            'param' => 'id',
            'help' => pht(
              'Build Target ID to attach the log to.'),
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
      "%s\n",
      pht('Reading log from stdin...'));

    $content = file_get_contents('php://stdin');
    $log->append($content);

    $log->closeBuildLog();

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
