<?php

final class PhabricatorProfileMenuCollapsedSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'profile-menu.collapsed';

  public function getSettingName() {
    return pht('Profile Menu Collapsed');
  }

}
