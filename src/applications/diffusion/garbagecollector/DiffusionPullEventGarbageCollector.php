<?php

final class DiffusionPullEventGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'diffusion.pull';

  public function getCollectorName() {
    return pht('Repository Pull Events');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('30 days in seconds');
  }

  protected function collectGarbage() {
    $table = new PhabricatorRepositoryPullEvent();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE epoch < %d LIMIT 100',
      $table->getTableName(),
      $this->getGarbageEpoch());

    return ($conn_w->getAffectedRows() == 100);
  }

}
