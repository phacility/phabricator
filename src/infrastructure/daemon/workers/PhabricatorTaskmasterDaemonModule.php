<?php

final class PhabricatorTaskmasterDaemonModule
  extends PhutilDaemonOverseerModule {

  public function shouldWakePool(PhutilDaemonPool $pool) {
    $class = $pool->getPoolDaemonClass();

    if ($class != 'PhabricatorTaskmasterDaemon') {
      return false;
    }

    if ($this->shouldThrottle($class, 1)) {
      return false;
    }

    $table = new PhabricatorWorkerActiveTask();
    $conn = $table->establishConnection('r');

    $row = queryfx_one(
      $conn,
      'SELECT id FROM %T WHERE leaseOwner IS NULL
        OR leaseExpires <= %d LIMIT 1',
      $table->getTableName(),
      PhabricatorTime::getNow());
    if (!$row) {
      return false;
    }

    return true;
  }

}
