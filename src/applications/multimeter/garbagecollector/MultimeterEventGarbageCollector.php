<?php

final class MultimeterEventGarbageCollector
  extends PhabricatorGarbageCollector {

  public function collectGarbage() {
    $ttl = phutil_units('90 days in seconds');

    $table = new MultimeterEvent();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE epoch < %d LIMIT 100',
      $table->getTableName(),
      PhabricatorTime::getNow() - $ttl);

    return ($conn_w->getAffectedRows() == 100);
  }

}
