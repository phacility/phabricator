<?php

final class PhabricatorPolicyFavoritesSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'policy.favorites';

  public function getSettingName() {
    return pht('Policy Favorites');
  }

  public function getSettingDefaultValue() {
    return array();
  }

}
