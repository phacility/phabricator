<?php

final class ConduitTokenGarbageCollector
  extends PhabricatorGarbageCollector {

  public function collectGarbage() {
    $table = new PhabricatorConduitToken();
    $conn_w = $table->establishConnection('w');
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE expires <= %d
        ORDER BY dateCreated ASC LIMIT 100',
      $table->getTableName(),
      PhabricatorTime::getNow());

    return ($conn_w->getAffectedRows() == 100);
  }

}
