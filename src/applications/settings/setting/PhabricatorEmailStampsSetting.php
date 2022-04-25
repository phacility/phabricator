<?php

final class PhabricatorEmailStampsSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'stamps';

  const VALUE_BODY_STAMPS = 'body';
  const VALUE_HEADER_STAMPS = 'header';

  public function getSettingName() {
    return pht('Send Stamps');
  }

  public function getSettingPanelKey() {
    return PhabricatorEmailFormatSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 400;
  }

  protected function getControlInstructions() {
    return pht(<<<EOREMARKUP
Outgoing mail is stamped with labels like `actor(alice)` which can be used to
write client mail rules to organize mail. By default, these stamps are sent
in an `X-Phabricator-Stamps` header.

If you use a client which can not use headers to route mail (like Gmail),
you can also include the stamps in the message body so mail rules based on
body content can route messages.
EOREMARKUP
      );
  }

  public function getSettingDefaultValue() {
    return self::VALUE_HEADER_STAMPS;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_HEADER_STAMPS => pht('Mail Headers'),
      self::VALUE_BODY_STAMPS => pht('Mail Headers and Body'),
    );
  }

}
