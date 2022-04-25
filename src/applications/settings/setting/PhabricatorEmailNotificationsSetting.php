<?php

final class PhabricatorEmailNotificationsSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'no-mail';

  const VALUE_SEND_MAIL = '0';
  const VALUE_NO_MAIL = '1';
  const VALUE_MOZILLA_MAIL = '2';

  public function getSettingName() {
    return pht('Email Notifications');
  }

  public function getSettingPanelKey() {
    return PhabricatorEmailDeliverySettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 100;
  }

  protected function getControlInstructions() {
    return pht(
      ' - To receive the new Mozilla-specific emails, select **Mozilla '.
      'Notifications**. Note that herald rules will no longer '.
      'send you emails.'.
      "\n".
      ' - To continue to receive regular Phabricator emails, including Herald '.
      'mail, choose **Legacy Phabricator Notifications**.'.
      "\n".
      ' - If you select **Disable Email Notifications**, this server will never '.
      'send email to notify you about events. This preference overrides '.
      'all your other settings.'.
      "\n\n".
      "//Regardless of this setting, you will still receive some ".
      "administrative email, like password reset email.//");
  }

  public function getSettingDefaultValue() {
    return PhabricatorEnv::getEnvConfig('email.default');
  }

  protected function getSelectOptions() {
    return self::getOptions();
  }

  public static function getOptions() {
    return array(
      self::VALUE_MOZILLA_MAIL => pht('Mozilla Notifications'),
      self::VALUE_SEND_MAIL => pht('Legacy Phabricator Notifications'),
      self::VALUE_NO_MAIL => pht('Disable Email Notifications'),
    );
  }

}
