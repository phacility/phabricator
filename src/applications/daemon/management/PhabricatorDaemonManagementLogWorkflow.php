<?php

final class PhabricatorDaemonManagementLogWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('log')
      ->setExamples('**log** [__options__]')
      ->setSynopsis(
        pht(
          'Print the logs for all daemons, or some daemon(s) identified by '.
          'ID. You can get the ID for a daemon from the Daemon Console in '.
          'the web interface.'))
      ->setArguments(
        array(
          array(
            'name'    => 'id',
            'param'   => 'id',
            'help'    => pht('Show logs for daemon(s) with given ID(s).'),
            'repeat'  => true,
          ),
          array(
            'name'    => 'limit',
            'param'   => 'N',
            'default' => 100,
            'help'    => pht(
              'Show a specific number of log messages (default 100).'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {

    $query = id(new PhabricatorDaemonLogQuery())
      ->setViewer($this->getViewer())
      ->setAllowStatusWrites(true);
    $ids = $args->getArg('id');
    if ($ids) {
      $query->withIDs($ids);
    }
    $daemons = $query->execute();

    if (!$daemons) {
      if ($ids) {
        throw new PhutilArgumentUsageException(
          pht('No daemon(s) with id(s) "%s" exist!', implode(', ', $ids)));
      } else {
        throw new PhutilArgumentUsageException(
          pht('No daemons are running.'));
      }
    }

    $console = PhutilConsole::getConsole();

    $limit = $args->getArg('limit');

    $logs = id(new PhabricatorDaemonLogEvent())->loadAllWhere(
      'logID IN (%Ld) ORDER BY id DESC LIMIT %d',
      mpull($daemons, 'getID'),
      $limit);
    $logs = array_reverse($logs);

    $lines = array();
    foreach ($logs as $log) {
      $text_lines = phutil_split_lines($log->getMessage(), $retain = false);
      foreach ($text_lines as $line) {
        $lines[] = array(
          'id' => $log->getLogID(),
          'type' => $log->getLogType(),
          'date' => $log->getEpoch(),
          'data' => $line,
        );
      }
    }

    // Each log message may be several lines. Limit the number of lines we
    // output so that `--limit 123` means "show 123 lines", which is the most
    // easily understandable behavior.
    $lines = array_slice($lines, -$limit);

    foreach ($lines as $line) {
      $id = $line['id'];
      $type = $line['type'];
      $data = $line['data'];
      $date = date('r', $line['date']);

      $console->writeOut(
        "%s\n",
        pht(
          'Daemon %d %s [%s] %s',
          $id,
          $type,
          $date,
          $data));
    }

    return 0;
  }


}
