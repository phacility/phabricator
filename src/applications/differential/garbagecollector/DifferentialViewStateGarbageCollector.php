<?php

final class DifferentialViewStateGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'differential.viewstate';

  public function getCollectorName() {
    return pht('Differential View States');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('180 days in seconds');
  }

  protected function collectGarbage() {
    $table = new DifferentialViewState();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'DELETE FROM %R WHERE dateModified < %d LIMIT 100',
      $table,
      $this->getGarbageEpoch());

    return ($conn->getAffectedRows() == 100);
  }

}
