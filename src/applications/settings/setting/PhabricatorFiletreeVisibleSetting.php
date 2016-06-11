<?php

final class PhabricatorFiletreeVisibleSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'nav-collapsed';

  public function getSettingName() {
    return pht('Filetree Visible');
  }

}
