<?php

final class PhabricatorDarkConsoleTabSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'darkconsole.tab';

  public function getSettingName() {
    return pht('DarkConsole Tab');
  }

}
