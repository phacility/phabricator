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

  public function getValueFromStorage($value) {
    return phutil_json_decode($value);
  }

  public function newValueForUsers($key, array $users) {
    $viewer = $this->getViewer();

    $user_phids = mpull($users, 'getPHID');

    $preferences = id(new PhabricatorUserPreferencesQuery())
      ->setViewer($viewer)
      ->withUserPHIDs($user_phids)
      ->execute();

    $settings = mpull($preferences, 'getPreferences', 'getUserPHID');

    $results = array();
    foreach ($user_phids as $user_phid) {
      $value = idx($settings, $user_phid, array());
      $results[$user_phid] = phutil_json_encode($value);
    }

    return $results;
  }

}
