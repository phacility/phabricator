<?php

final class PhabricatorDateFormatSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'date-format';

  const VALUE_FORMAT_ISO = 'Y-m-d';
  const VALUE_FORMAT_US = 'n/j/Y';
  const VALUE_FORMAT_EUROPE = 'd-m-Y';

  public function getSettingName() {
    return pht('Date Format');
  }

  public function getSettingPanelKey() {
    return PhabricatorDateTimeSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 200;
  }

  protected function getControlInstructions() {
    return pht(
      'Select the format you prefer for editing dates.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_FORMAT_ISO;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_FORMAT_ISO => pht('ISO 8601: 2000-02-28'),
      self::VALUE_FORMAT_US => pht('US: 2/28/2000'),
      self::VALUE_FORMAT_EUROPE => pht('Europe: 28-02-2000'),
    );
  }


}
