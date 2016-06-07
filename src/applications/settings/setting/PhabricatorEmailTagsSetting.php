<?php

final class PhabricatorEmailTagsSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'mailtags';

  // These are in an unusual order for historic reasons.
  const VALUE_NOTIFY = 0;
  const VALUE_EMAIL = 1;
  const VALUE_IGNORE = 2;

  public function getSettingName() {
    return pht('Mail Tags');
  }

  public function getSettingDefaultValue() {
    return array();
  }

}
