<?php

final class HarbormasterManagementRebuildLogWorkflow
  extends HarbormasterManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('rebuild-log')
      ->setExamples('**rebuild-log** --id __id__ [__options__]')
      ->setSynopsis(
        pht(
          'Rebuild the file and summary for a log. This is primarily '.
          'intended to make it easier to develop new log summarizers.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'help' => pht('Log to rebuild.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $log_id = $args->getArg('id');
    if (!$log_id) {
      throw new PhutilArgumentUsageException(
        pht('Choose a build log to rebuild with "--id".'));
    }

    $log = id(new HarbormasterBuildLogQuery())
      ->setViewer($viewer)
      ->withIDs(array($log_id))
      ->executeOne();
    if (!$log) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to load build log "%s".',
          $log_id));
    }

    PhabricatorWorker::setRunAllTasksInProcess(true);
    $log->scheduleRebuild(true);

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
