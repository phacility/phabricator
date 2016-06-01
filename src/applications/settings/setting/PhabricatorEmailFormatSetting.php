<?php

final class PhabricatorEmailFormatSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'html-emails';

  const VALUE_HTML_EMAIL = 'true';
  const VALUE_TEXT_EMAIL = 'false';

  public function getSettingName() {
    return pht('HTML Email');
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
