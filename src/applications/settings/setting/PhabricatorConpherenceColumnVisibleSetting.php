<?php

final class PhabricatorConpherenceColumnVisibleSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'conpherence-column';

  public function getSettingName() {
    return pht('Conpherence Column Visible');
  }

}
