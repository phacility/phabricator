<?php

final class PhabricatorUserCachePurger
  extends PhabricatorCachePurger {

  const PURGERKEY = 'user';

  public function purgeCache() {
    $table = new PhabricatorUserCache();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'TRUNCATE TABLE %T',
      $table->getTableName());
  }

}
