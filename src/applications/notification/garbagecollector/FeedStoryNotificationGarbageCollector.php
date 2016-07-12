<?php

final class FeedStoryNotificationGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'feed.notifications';

  public function getCollectorName() {
    return pht('Notifications');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('90 days in seconds');
  }

  protected function collectGarbage() {
    $table = new PhabricatorFeedStoryNotification();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE chronologicalKey < (%d << 32)
        ORDER BY chronologicalKey ASC LIMIT 100',
      $table->getTableName(),
      $this->getGarbageEpoch());

    return ($conn_w->getAffectedRows() == 100);
  }

}
