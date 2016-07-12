<?php

final class PhabricatorTitleGlyphsSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'titles';

  const VALUE_TITLE_GLYPHS = 'glyph';
  const VALUE_TITLE_TEXT = 'text';

  public function getSettingName() {
    return pht('Page Titles');
  }

  public function getSettingPanelKey() {
    return PhabricatorDisplayPreferencesSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 200;
  }

  protected function getControlInstructions() {
    return pht(
      'Phabricator uses unicode glyphs in page titles to provide a compact '.
      'representation of the current application. You can substitute plain '.
      'text instead if these glyphs do not display on your system.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_TITLE_GLYPHS;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_TITLE_GLYPHS => pht("Use Unicode Glyphs: \xE2\x9A\x99"),
      self::VALUE_TITLE_TEXT => pht('Use Plain Text: [Differential]'),
    );
  }

}
