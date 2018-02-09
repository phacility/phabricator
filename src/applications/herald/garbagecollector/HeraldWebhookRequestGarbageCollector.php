<?php

final class HeraldWebhookRequestGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'herald.webhooks';

  public function getCollectorName() {
    return pht('Herald Webhooks');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('7 days in seconds');
  }

  protected function collectGarbage() {
    $table = new HeraldWebhookRequest();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE dateCreated < %d LIMIT 100',
      $table->getTableName(),
      $this->getGarbageEpoch());

    return ($conn_w->getAffectedRows() == 100);
  }

}
