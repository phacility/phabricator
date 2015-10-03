<?php

final class PhabricatorCacheTTLGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'cache.general.ttl';

  public function getCollectorName() {
    return pht('General Cache (TTL)');
  }

  public function hasAutomaticPolicy() {
    return true;
  }

  protected function collectGarbage() {
    $cache = new PhabricatorKeyValueDatabaseCache();
    $conn_w = $cache->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE cacheExpires < %d
        ORDER BY cacheExpires ASC LIMIT 100',
      $cache->getTableName(),
      PhabricatorTime::getNow());

    return ($conn_w->getAffectedRows() == 100);
  }

}
