<?php

final class PhabricatorEmailSelfActionsSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'self-mail';

  const VALUE_SEND_SELF = '0';
  const VALUE_NO_SELF = '1';

  public function getSettingName() {
    return pht('Self Actions');
  }

  public function getSettingPanelKey() {
    return PhabricatorEmailDeliverySettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 200;
  }

  protected function getControlInstructions() {
    return pht(
      'If you disable **Self Actions**, Phabricator will not notify '.
      'you about actions you take.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_SEND_SELF;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_SEND_SELF => pht('Enable Self Action Mail'),
      self::VALUE_NO_SELF => pht('Disable Self Action Mail'),
    );
  }

}
