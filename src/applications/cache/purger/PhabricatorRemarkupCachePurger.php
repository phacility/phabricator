<?php

final class PhabricatorRemarkupCachePurger
  extends PhabricatorCachePurger {

  const PURGERKEY = 'remarkup';

  public function purgeCache() {
    $table = new PhabricatorMarkupCache();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'TRUNCATE TABLE %T',
      $table->getTableName());
  }

}
