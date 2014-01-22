<?php

final class PhabricatorDaemonManagementLogWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('log')
      ->setExamples('**log** __id__')
      ->setSynopsis(
        pht(
          'Print the log for a daemon, identified by ID. You can get the '.
          'ID for a daemon from the Daemon Console in the web interface.'))
      ->setArguments(
        array(
          array(
            'name' => 'daemon',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $id = $args->getArg('daemon');
    if (!$id) {
      throw new PhutilArgumentUsageException(
        pht('You must specify the daemon ID to show logs for.'));
    } else if (count($id) > 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify exactly one daemon ID to show logs for.'));
    }
    $id = head($id);

    $daemon = id(new PhabricatorDaemonLogQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($id))
      ->setAllowStatusWrites(true)
      ->executeOne();

    if (!$daemon) {
      throw new PhutilArgumentUsageException(
        pht('No such daemon with id "%s"!', $id));
    }

    $console = PhutilConsole::getConsole();
    $logs = id(new PhabricatorDaemonLogEvent())->loadAllWhere(
      'logID = %d ORDER BY id ASC',
      $daemon->getID());

    $lines = array();
    foreach ($logs as $log) {
      $text_lines = phutil_split_lines($log->getMessage(), $retain = false);
      foreach ($text_lines as $line) {
        $lines[] = array(
          'type' => $log->getLogType(),
          'date' => $log->getEpoch(),
          'data' => $line,
        );
      }
    }

    foreach ($lines as $line) {
      $type = $line['type'];
      $data = $line['data'];
      $date = date('r', $line['date']);

      $console->writeOut(
        "%s\n",
        sprintf(
          '[%s] %s %s',
          $date,
          $type,
          $data));
    }

    return 0;
  }


}
