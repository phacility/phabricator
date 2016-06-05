<?php

final class PhabricatorUserNotificationCountCacheType
  extends PhabricatorUserCacheType {

  const CACHETYPE = 'notification.count';

  const KEY_COUNT = 'user.notification.count.v1';

  public function getAutoloadKeys() {
    return array(
      self::KEY_COUNT,
    );
  }

  public function canManageKey($key) {
    return ($key === self::KEY_COUNT);
  }

  public function getValueFromStorage($value) {
    return (int)$value;
  }

  public function newValueForUsers($key, array $users) {
    if (!$users) {
      return array();
    }

    $user_phids = mpull($users, 'getPHID');

    $table = new PhabricatorFeedStoryNotification();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT userPHID, COUNT(*) N FROM %T
        WHERE userPHID IN (%Ls) AND hasViewed = 0
        GROUP BY userPHID',
      $table->getTableName(),
      $user_phids);

    $empty = array_fill_keys($user_phids, 0);
    return ipull($rows, 'N', 'userPHID') + $empty;
  }

}
