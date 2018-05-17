<?php

final class PhabricatorExportFormatSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'export.format';

  public function getSettingName() {
    return pht('Export Format');
  }

  public function getSettingDefaultValue() {
    return null;
  }

}
