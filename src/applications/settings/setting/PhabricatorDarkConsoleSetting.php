<?php

final class PhabricatorDarkConsoleSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'dark_console';

  const VALUE_DARKCONSOLE_DISABLED = '0';
  const VALUE_DARKCONSOLE_ENABLED = '1';

  public function getSettingName() {
    return pht('DarkConsole');
  }

  public function getSettingPanelKey() {
    return PhabricatorDeveloperPreferencesSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 100;
  }

  protected function isEnabledForViewer(PhabricatorUser $viewer) {
    return PhabricatorEnv::getEnvConfig('darkconsole.enabled');
  }

  protected function getControlInstructions() {
    return pht(
      'DarkConsole is a debugging console for developing and troubleshooting '.
      'applications. After enabling DarkConsole, press the '.
      '{nav `} key on your keyboard to toggle it on or off.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_DARKCONSOLE_DISABLED;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_DARKCONSOLE_DISABLED => pht('Disable DarkConsole'),
      self::VALUE_DARKCONSOLE_ENABLED => pht('Enable DarkConsole'),
    );
  }

  public function expandSettingTransaction($object, $xaction) {
    // If the user has hidden the DarkConsole UI, forget their setting when
    // they enable or disable it.
    return array(
      $xaction,
      $this->newSettingTransaction(
        $object,
        PhabricatorDarkConsoleVisibleSetting::SETTINGKEY,
        1),
    );
  }


}
