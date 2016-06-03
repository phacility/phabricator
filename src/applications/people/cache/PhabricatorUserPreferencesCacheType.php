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

    $preferences = id(new PhabricatorUserPreferencesQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(mpull($users, 'getPHID'))
      ->execute();

    return mpull($preferences, 'getPreferences', 'getUserPHID');
  }

}
