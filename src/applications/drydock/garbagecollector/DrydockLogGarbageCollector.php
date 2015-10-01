<?php

final class DrydockLogGarbageCollector
  extends PhabricatorGarbageCollector {

  public function collectGarbage() {
    $log_table = new DrydockLog();
    $conn_w = $log_table->establishConnection('w');

    $now = PhabricatorTime::getNow();
    $ttl = phutil_units('30 days in seconds');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE epoch <= %d LIMIT 100',
      $log_table->getTableName(),
      $now - $ttl);

    return ($conn_w->getAffectedRows() == 100);
  }

}
