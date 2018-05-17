<?php

final class HarbormasterManagementRebuildLogWorkflow
  extends HarbormasterManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('rebuild-log')
      ->setExamples(
        pht(
          "**rebuild-log** --id __id__ [__options__]\n".
          "**rebuild-log** --all"))
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
          array(
            'name' => 'all',
            'help' => pht('Rebuild all logs.'),
          ),
          array(
            'name' => 'force',
            'help' => pht(
              'Force logs to rebuild even if they appear to be in good '.
              'shape already.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $is_force = $args->getArg('force');

    $log_id = $args->getArg('id');
    $is_all = $args->getArg('all');

    if (!$is_all && !$log_id) {
      throw new PhutilArgumentUsageException(
        pht(
          'Choose a build log to rebuild with "--id", or rebuild all '.
          'logs with "--all".'));
    }

    if ($is_all && $log_id) {
      throw new PhutilArgumentUsageException(
        pht(
          'You can not specify both "--id" and "--all". Choose one or '.
          'the other.'));
    }

    if ($log_id) {
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
      $logs = array($log);
    } else {
      $logs = new LiskMigrationIterator(new HarbormasterBuildLog());
    }

    PhabricatorWorker::setRunAllTasksInProcess(true);

    foreach ($logs as $log) {
      echo tsprintf(
        "%s\n",
        pht(
          'Rebuilding log "%s"...',
          pht('Build Log %d', $log->getID())));

      try {
        $log->scheduleRebuild($is_force);
      } catch (Exception $ex) {
        if ($is_all) {
          phlog($ex);
        } else {
          throw $ex;
        }
      }
    }

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
