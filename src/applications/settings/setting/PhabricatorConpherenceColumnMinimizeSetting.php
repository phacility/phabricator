<?php

final class PhabricatorConpherenceColumnMinimizeSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'conpherence-minimize-column';

  public function getSettingName() {
    return pht('Conpherence Column Minimize');
  }

}
