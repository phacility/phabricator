<?php

final class PhabricatorDaemonLogEventGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'daemon.processes';

  public function getCollectorName() {
    return pht('Daemon Processes');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('7 days in seconds');
  }

  protected function collectGarbage() {
    $table = new PhabricatorDaemonLogEvent();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE epoch < %d LIMIT 100',
      $table->getTableName(),
      $this->getGarbageEpoch());

    return ($conn_w->getAffectedRows() == 100);
  }

}
