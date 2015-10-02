<?php

final class PhabricatorAuthTemporaryTokenGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'auth.tokens';

  public function getCollectorName() {
    return pht('Authentication Tokens');
  }

  public function hasAutomaticPolicy() {
    return true;
  }

  protected function collectGarbage() {
    $session_table = new PhabricatorAuthTemporaryToken();
    $conn_w = $session_table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE tokenExpires <= UNIX_TIMESTAMP() LIMIT 100',
      $session_table->getTableName());

    return ($conn_w->getAffectedRows() == 100);
  }

}
