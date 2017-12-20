<?php

final class PhabricatorNotificationsSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'desktop-notifications';

  const WEB_ONLY = 0;
  const WEB_AND_DESKTOP = 1;
  const DESKTOP_ONLY = 2;
  const NONE = 3;

  public function getSettingName() {
    return pht('Notifications');
  }

  public static function getOptionsMap() {
    return array(
      self::WEB_ONLY => pht('Web Only'),
      self::WEB_AND_DESKTOP => pht('Web and Desktop'),
      self::DESKTOP_ONLY => pht('Desktop Only'),
      self::NONE => pht('No Notifications'),
    );
  }

  public static function desktopReady($option) {
    switch ($option) {
      case self::WEB_AND_DESKTOP:
      case self::DESKTOP_ONLY:
        return true;
    }
    return false;
  }

  public static function webReady($option) {
    switch ($option) {
      case self::WEB_AND_DESKTOP:
      case self::WEB_ONLY:
        return true;
    }
    return false;
  }

}
