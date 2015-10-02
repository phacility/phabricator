<?php

final class PhabricatorCacheMarkupGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'cache.markup';

  public function getCollectorName() {
    return pht('Markup Cache');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('30 days in seconds');
  }

  protected function collectGarbage() {
    $table = new PhabricatorMarkupCache();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d LIMIT 100',
      $table->getTableName(),
      $this->getGarbageEpoch());

    return ($conn_w->getAffectedRows() == 100);
  }

}
