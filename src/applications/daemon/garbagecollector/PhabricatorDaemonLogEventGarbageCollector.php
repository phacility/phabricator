<?php

final class PhabricatorDaemonLogEventGarbageCollector
  extends PhabricatorGarbageCollector {

  public function collectGarbage() {
    $ttl = PhabricatorEnv::getEnvConfig('gcdaemon.ttl.daemon-logs');
    if ($ttl <= 0) {
      return false;
    }

    $table = new PhabricatorDaemonLogEvent();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE epoch < %d LIMIT 100',
      $table->getTableName(),
      time() - $ttl);

    return ($conn_w->getAffectedRows() == 100);
  }

}
