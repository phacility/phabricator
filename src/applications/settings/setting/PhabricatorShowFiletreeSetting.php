<?php

final class PhabricatorShowFiletreeSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'diff-filetree';

  const VALUE_DISABLE_FILETREE = 0;
  const VALUE_ENABLE_FILETREE = 1;

  public function getSettingName() {
    return pht('Show Filetree');
  }

  protected function getSettingOrder() {
    return 300;
  }

  public function getSettingPanelKey() {
    return PhabricatorDiffPreferencesSettingsPanel::PANELKEY;
  }

  protected function getControlInstructions() {
    return pht(
      'When viewing a revision or commit, you can enable a sidebar showing '.
      'affected files. When this option is enabled, press {nav %s} to show '.
      'or hide the sidebar.',
      'f');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_DISABLE_FILETREE;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_DISABLE_FILETREE => pht('Disable Filetree'),
      self::VALUE_ENABLE_FILETREE => pht('Enable Filetree'),
    );
  }

}
