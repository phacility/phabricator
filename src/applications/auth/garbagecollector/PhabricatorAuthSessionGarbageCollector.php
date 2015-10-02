<?php

final class PhabricatorAuthSessionGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'auth.sessions';

  public function getCollectorName() {
    return pht('Authentication Sessions');
  }

  public function hasAutomaticPolicy() {
    return true;
  }

  protected function collectGarbage() {
    $session_table = new PhabricatorAuthSession();
    $conn_w = $session_table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE sessionExpires <= UNIX_TIMESTAMP() LIMIT 100',
      $session_table->getTableName());

    return ($conn_w->getAffectedRows() == 100);
  }

}
