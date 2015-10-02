<?php

final class DrydockLogGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'drydock.logs';

  public function getCollectorName() {
    return pht('Drydock Logs');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('30 days in seconds');
  }

  protected function collectGarbage() {
    $log_table = new DrydockLog();
    $conn_w = $log_table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE epoch <= %d LIMIT 100',
      $log_table->getTableName(),
      $this->getGarbageEpoch());

    return ($conn_w->getAffectedRows() == 100);
  }

}
