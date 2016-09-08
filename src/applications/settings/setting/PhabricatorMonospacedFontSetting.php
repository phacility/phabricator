<?php

final class PhabricatorMonospacedFontSetting
  extends PhabricatorStringSetting {

  const SETTINGKEY = 'monospaced';

  public function getSettingName() {
    return pht('Monospaced Font');
  }

  public function getSettingPanelKey() {
    return PhabricatorDisplayPreferencesSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 500;
  }

  protected function getControlInstructions() {
    return pht(
      'You can customize the font used when showing monospaced text, '.
      'including source code. You should enter a valid CSS font declaration '.
      'like: `13px Consolas`');
  }

  public function validateTransactionValue($value) {
    if (!strlen($value)) {
      return;
    }

    $filtered = self::filterMonospacedCSSRule($value);
    if ($filtered !== $value) {
      throw new Exception(
        pht(
          'Monospaced font value "%s" is unsafe. You may only enter '.
          'letters, numbers, spaces, commas, periods, hyphens, '.
          'forward slashes, and double quotes',
          $value));
    }
  }

  public static function filterMonospacedCSSRule($monospaced) {
    // Prevent the user from doing dangerous things.
    return preg_replace('([^a-z0-9 ,"./-]+)i', '', $monospaced);
  }

}
