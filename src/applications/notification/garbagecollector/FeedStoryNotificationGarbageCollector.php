<?php

final class FeedStoryNotificationGarbageCollector
  extends PhabricatorGarbageCollector {

  public function collectGarbage() {
    $ttl = 90 * 24 * 60 * 60;

    $table = new PhabricatorFeedStoryNotification();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE chronologicalKey < (%d << 32)
        ORDER BY chronologicalKey ASC LIMIT 100',
      $table->getTableName(),
      time() - $ttl);

    return ($conn_w->getAffectedRows() == 100);
  }

}
