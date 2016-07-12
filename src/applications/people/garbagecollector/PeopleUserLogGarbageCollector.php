<?php

final class PeopleUserLogGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'user.logs';

  public function getCollectorName() {
    return pht('User Activity Logs');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('180 days in seconds');
  }

  protected function collectGarbage() {
    $table = new PhabricatorUserLog();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d LIMIT 100',
      $table->getTableName(),
      $this->getGarbageEpoch());

    return ($conn_w->getAffectedRows() == 100);
  }

}
