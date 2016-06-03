<?php

final class PhabricatorUserPreferencesCacheType
  extends PhabricatorUserCacheType {

  const CACHETYPE = 'preferences';

  const KEY_PREFERENCES = 'user.preferences.v1';

  public function getAutoloadKeys() {
    return array(
      self::KEY_PREFERENCES,
    );
  }

  public function canManageKey($key) {
    return ($key === self::KEY_PREFERENCES);
  }

  public function newValueForUsers($key, array $users) {
    $viewer = $this->getViewer();

    $user_phids = mpull($users, 'getPHID');

    $preferences = id(new PhabricatorUserPreferencesQuery())
      ->setViewer($viewer)
      ->withUserPHIDs($user_phids)
      ->execute();

    $empty = array_fill_keys($user_phids, array());

    return mpull($preferences, 'getPreferences', 'getUserPHID') + $empty;
  }

}
