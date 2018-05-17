<?php

final class PhabricatorFiletreeWidthSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'filetree.width';

  public function getSettingName() {
    return pht('Filetree Width');
  }

}
