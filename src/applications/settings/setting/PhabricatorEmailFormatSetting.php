<?php

final class PhabricatorEmailFormatSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'html-emails';

  const VALUE_HTML_EMAIL = 'html';
  const VALUE_TEXT_EMAIL = 'text';

  public function getSettingName() {
    return pht('HTML Email');
  }

  public function getSettingPanelKey() {
    return PhabricatorEmailFormatSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 100;
  }

  protected function getControlInstructions() {
    return pht(
      'You can opt to receive plain text email from Phabricator instead '.
      'of HTML email. Plain text email works better with some clients.');
  }

  public function getSettingDefaultValue() {
    return self::VALUE_HTML_EMAIL;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_HTML_EMAIL => pht('Send HTML Email'),
      self::VALUE_TEXT_EMAIL => pht('Send Plain Text Email'),
    );
  }

}
