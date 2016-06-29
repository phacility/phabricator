<?php

final class PhabricatorDarkConsoleVisibleSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'darkconsole.visible';

  public function getSettingName() {
    return pht('DarkConsole Visible');
  }

}
