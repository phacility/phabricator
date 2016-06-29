<?php

final class PhabricatorSearchScopeSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'search-scope';

  public function getSettingName() {
    return pht('Search Scope');
  }

  public function getSettingDefaultValue() {
    return 'all';
  }

}
