<?php

final class DifferentialParseCacheGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'differential.parse';

  public function getCollectorName() {
    return pht('Differential Parse Cache');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('14 days in seconds');
  }

  public function collectGarbage() {
    $key = 'gcdaemon.ttl.differential-parse-cache';
    $ttl = PhabricatorEnv::getEnvConfig($key);
    if ($ttl <= 0) {
      return false;
    }

    $table = new DifferentialChangeset();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d LIMIT 100',
      DifferentialChangeset::TABLE_CACHE,
      time() - $ttl);

    return ($conn_w->getAffectedRows() == 100);
  }

}
