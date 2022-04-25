<?php

final class PhabricatorOlderInlinesSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'diff-ghosts';

  const VALUE_GHOST_INLINES_ENABLED = 'default';
  const VALUE_GHOST_INLINES_DISABLED = 'disabled';

  public function getSettingName() {
    return pht('Show Older Inlines');
  }

  protected function getSettingOrder() {
    return 200;
  }

  public function getSettingPanelKey() {
    return PhabricatorDiffPreferencesSettingsPanel::PANELKEY;
  }

  protected function getControlInstructions() {
    return pht(
      'When a revision is updated, this software attempts to bring inline '.
      'comments on the older version forward to the new changes. You can '.
      'disable this behavior if you prefer comments stay anchored in one '.
      'place.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_GHOST_INLINES_ENABLED;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_GHOST_INLINES_ENABLED => pht('Enabled'),
      self::VALUE_GHOST_INLINES_DISABLED => pht('Disabled'),
    );
  }


}
