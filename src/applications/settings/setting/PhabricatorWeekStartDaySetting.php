<?php

final class PhabricatorWeekStartDaySetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'week-start-day';

  public function getSettingName() {
    return pht('Week Starts On');
  }

  public function getSettingPanelKey() {
    return PhabricatorDateTimeSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 400;
  }

  protected function getControlInstructions() {
    return pht(
      'Choose which day a calendar week should begin on.');
  }

  public function getSettingDefaultValue() {
    return 0;
  }

  protected function getSelectOptions() {
    return array(
      0 => pht('Sunday'),
      1 => pht('Monday'),
      2 => pht('Tuesday'),
      3 => pht('Wednesday'),
      4 => pht('Thursday'),
      5 => pht('Friday'),
      6 => pht('Saturday'),
    );
  }

}
