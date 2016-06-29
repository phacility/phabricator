<?php

final class PhabricatorTimeFormatSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'time-format';

  const VALUE_FORMAT_12HOUR = 'g:i A';
  const VALUE_FORMAT_24HOUR = 'H:i';

  public function getSettingName() {
    return pht('Time Format');
  }

  public function getSettingPanelKey() {
    return PhabricatorDateTimeSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 300;
  }

  protected function getControlInstructions() {
    return pht(
      'Select the format you prefer for editing and displaying time.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_FORMAT_12HOUR;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_FORMAT_12HOUR => pht('12 Hour, 2:34 PM'),
      self::VALUE_FORMAT_24HOUR => pht('24 Hour, 14:34'),
    );
  }


}
