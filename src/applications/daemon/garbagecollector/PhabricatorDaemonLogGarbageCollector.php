<?php

final class PhabricatorDaemonLogGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'daemon.logs';

  public function getCollectorName() {
    return pht('Daemon Logs');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('7 days in seconds');
  }

  protected function collectGarbage() {
    $table = new PhabricatorDaemonLog();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateModified < %d LIMIT 100',
      $table->getTableName(),
      $this->getGarbageEpoch());

    return ($conn_w->getAffectedRows() == 100);
  }

}
