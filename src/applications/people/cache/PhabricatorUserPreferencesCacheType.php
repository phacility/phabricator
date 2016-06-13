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

    $users = mpull($users, null, 'getPHID');
    $user_phids = array_keys($users);

    $preferences = id(new PhabricatorUserPreferencesQuery())
      ->setViewer($viewer)
      ->withUserPHIDs($user_phids)
      ->execute();
    $preferences = mpull($preferences, null, 'getUserPHID');

    // If some users don't have settings of their own yet, we need to load
    // the global default settings to generate caches for them.
    if (count($preferences) < count($user_phids)) {
      $global = id(new PhabricatorUserPreferencesQuery())
        ->setViewer($viewer)
        ->withBuiltinKeys(
          array(
            PhabricatorUserPreferences::BUILTIN_GLOBAL_DEFAULT,
          ))
        ->executeOne();
    } else {
      $global = null;
    }

    $all_settings = PhabricatorSetting::getAllSettings();

    $settings = array();
    foreach ($users as $user_phid => $user) {
      $preference = idx($preferences, $user_phid, $global);

      if (!$preference) {
        continue;
      }

      foreach ($all_settings as $key => $setting) {
        $value = $preference->getSettingValue($key);

        // As an optimization, we omit the value from the cache if it is
        // exactly the same as the hardcoded default.
        $default_value = id(clone $setting)
          ->setViewer($user)
          ->getSettingDefaultValue();
        if ($value === $default_value) {
          continue;
        }

        $settings[$user_phid][$key] = $value;
      }
    }

    $results = array();
    foreach ($user_phids as $user_phid) {
      $value = idx($settings, $user_phid, array());
      $results[$user_phid] = phutil_json_encode($value);
    }

    return $results;
  }

}
