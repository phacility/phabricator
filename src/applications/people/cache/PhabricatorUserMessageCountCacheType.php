<?php

final class PhabricatorUserMessageCountCacheType
  extends PhabricatorUserCacheType {

  const CACHETYPE = 'message.count';

  const KEY_COUNT = 'user.message.count.v1';

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

    $unread = id(new ConpherenceParticipantCountQuery())
      ->withParticipantPHIDs($user_phids)
      ->withUnread(true)
      ->execute();

    $empty = array_fill_keys($user_phids, 0);
    return $unread + $empty;
  }

}
