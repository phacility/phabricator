<?php

final class PhabricatorEmailRePrefixSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 're-prefix';

  const VALUE_RE_PREFIX = 'true';
  const VALUE_NO_PREFIX = 'false';

  public function getSettingName() {
    return pht('Add "Re:" Prefix');
  }

  protected function getControlInstructions() {
    return pht(
      'The **Add "Re:" Prefix** setting adds "Re:" in front of all messages, '.
      'even if they are not replies. If you use **Mail.app** on Mac OS X, '.
      'this may improve mail threading.'.
      "\n\n".
      "| Setting                | Example Mail Subject\n".
      "|------------------------|----------------\n".
      "| Enable \"Re:\" Prefix  | ".
      "`Re: [Differential] [Accepted] D123: Example Revision`\n".
      "| Disable \"Re:\" Prefix | ".
      "`[Differential] [Accepted] D123: Example Revision`");
  }

  public function getSettingDefaultValue() {
    return self::VALUE_RE_PREFIX;
  }

  protected function getSelectOptions() {
    return array(
      self::VALUE_RE_PREFIX => pht('Enable "Re:" Prefix'),
      self::VALUE_NO_PREFIX => pht('Disable "Re:" Prefix'),
    );
  }

}
