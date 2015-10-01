<?php

final class ConduitConnectionGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'conduit.connections';

  public function getCollectorName() {
    return pht('Conduit Connections');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('180 days in seconds');
  }

  public function collectGarbage() {
    $key = 'gcdaemon.ttl.conduit-logs';
    $ttl = PhabricatorEnv::getEnvConfig($key);
    if ($ttl <= 0) {
      return false;
    }

    $table = new PhabricatorConduitConnectionLog();
    $conn_w = $table->establishConnection('w');
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d
        ORDER BY dateCreated ASC LIMIT 100',
      $table->getTableName(),
      time() - $ttl);

    return ($conn_w->getAffectedRows() == 100);
  }

}
