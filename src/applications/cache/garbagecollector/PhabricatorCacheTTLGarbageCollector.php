<?php

final class PhabricatorCacheTTLGarbageCollector
  extends PhabricatorGarbageCollector {

  public function collectGarbage() {
    $cache = new PhabricatorKeyValueDatabaseCache();
    $conn_w = $cache->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE cacheExpires < %d
        ORDER BY cacheExpires ASC LIMIT 100',
      $cache->getTableName(),
      time());

    return ($conn_w->getAffectedRows() == 100);
  }

}
