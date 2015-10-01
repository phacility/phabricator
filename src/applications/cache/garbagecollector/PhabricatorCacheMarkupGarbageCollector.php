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

  public function collectGarbage() {
    $key = 'gcdaemon.ttl.markup-cache';
    $ttl = PhabricatorEnv::getEnvConfig($key);
    if ($ttl <= 0) {
      return false;
    }

    $table = new PhabricatorMarkupCache();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d LIMIT 100',
      $table->getTableName(),
      time() - $ttl);

    return ($conn_w->getAffectedRows() == 100);
  }

}
