<?php

final class PhabricatorTimezoneIgnoreOffsetSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'time.offset.ignore';

  public function getSettingName() {
    return pht('Timezone Ignored Offset');
  }

}
