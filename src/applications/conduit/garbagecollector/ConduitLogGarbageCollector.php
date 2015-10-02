<?php

final class ConduitLogGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'conduit.logs';

  public function getCollectorName() {
    return pht('Conduit Logs');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('180 days in seconds');
  }

  protected function collectGarbage() {
    $table = new PhabricatorConduitMethodCallLog();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d
        ORDER BY dateCreated ASC LIMIT 100',
      $table->getTableName(),
      $this->getGarbageEpoch());

    return ($conn_w->getAffectedRows() == 100);
  }

}
