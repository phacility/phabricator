<?php

final class PhabricatorEditorMultipleSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'multiedit';

  const VALUE_SPACES = 'spaces';
  const VALUE_SINGLE = 'disable';

  public function getSettingName() {
    return pht('Edit Multiple Files');
  }

  public function getSettingPanelKey() {
    return PhabricatorDisplayPreferencesSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 400;
  }

  protected function getControlInstructions() {
    return pht(
      'Some editors support opening multiple files with a single URI. You '.
      'can specify the behavior of your editor here.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_SPACES;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_SPACES => pht('Supported, Separated by Spaces'),
      self::VALUE_SINGLE => pht('Not Supported'),
    );
  }

}
