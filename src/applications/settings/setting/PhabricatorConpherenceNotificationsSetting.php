<?php

final class PhabricatorConpherenceNotificationsSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'conph-notifications';

  const VALUE_CONPHERENCE_EMAIL = '0';
  const VALUE_CONPHERENCE_NOTIFY = '1';

  public function getSettingName() {
    return pht('Conpherence Notifications');
  }

  public function getSettingPanelKey() {
    return PhabricatorConpherencePreferencesSettingsPanel::PANELKEY;
  }

  protected function getControlInstructions() {
    return pht(
      'Choose the default notification behavior for Conpherence rooms.');
  }

  protected function isEnabledForViewer(PhabricatorUser $viewer) {
    return PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorConpherenceApplication',
      $viewer);
  }

  public function getSettingDefaultValue() {
    return self::VALUE_CONPHERENCE_EMAIL;
  }

  protected function getSelectOptions() {
    return self::getOptionsMap();
  }

  public static function getSettingLabel($key) {
    $labels = self::getOptionsMap();
    return idx($labels, $key, pht('Unknown ("%s")', $key));
  }

  private static function getOptionsMap() {
    return array(
      self::VALUE_CONPHERENCE_EMAIL => pht('Send Email'),
      self::VALUE_CONPHERENCE_NOTIFY => pht('Send Notifications'),
    );
  }

}
