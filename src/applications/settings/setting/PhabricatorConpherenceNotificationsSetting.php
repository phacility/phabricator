<?php

final class PhabricatorConpherenceNotificationsSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'conph-notifications';

  const VALUE_CONPHERENCE_EMAIL = '0';
  const VALUE_CONPHERENCE_NOTIFY = '1';

  public function getSettingName() {
    return pht('Conpherence Notifications');
  }

  protected function getControlInstructions() {
    return pht(
      'Choose the default notification behavior for Conpherence rooms.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_CONPHERENCE_EMAIL;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_CONPHERENCE_EMAIL => pht('Send Email'),
      self::VALUE_CONPHERENCE_NOTIFY => pht('Send Notifications'),
    );
  }

}
