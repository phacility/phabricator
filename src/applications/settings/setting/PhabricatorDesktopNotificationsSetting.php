<?php

final class PhabricatorDesktopNotificationsSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'desktop-notifications';

  public function getSettingName() {
    return pht('Desktop Notifications');
  }

}
