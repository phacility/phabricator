<?php

final class PhabricatorUnifiedDiffsSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'diff-unified';

  const VALUE_ON_SMALL_SCREENS = 'default';
  const VALUE_ALWAYS_UNIFIED = 'unified';

  public function getSettingName() {
    return pht('Show Unified Diffs');
  }

  protected function getSettingOrder() {
    return 100;
  }

  public function getSettingPanelKey() {
    return PhabricatorDiffPreferencesSettingsPanel::PANELKEY;
  }

  protected function getControlInstructions() {
    return pht(
      'Phabricator normally shows diffs in a side-by-side layout on large '.
      'screens and automatically switches to a unified view on small '.
      'screens (like mobile phones). If you prefer unified diffs even on '.
      'large screens, you can select them for use on all displays.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_ON_SMALL_SCREENS;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_ON_SMALL_SCREENS => pht('On Small Screens'),
      self::VALUE_ALWAYS_UNIFIED => pht('Always'),
    );
  }


}
