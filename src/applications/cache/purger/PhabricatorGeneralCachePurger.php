<?php

final class PhabricatorGeneralCachePurger
  extends PhabricatorCachePurger {

  const PURGERKEY = 'general';

  public function purgeCache() {
    $table = new PhabricatorMarkupCache();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'TRUNCATE TABLE %T',
      'cache_general');
  }

}
