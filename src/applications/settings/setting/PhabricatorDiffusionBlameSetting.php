<?php

final class PhabricatorDiffusionBlameSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'diffusion-blame';

  public function getSettingName() {
    return pht('Diffusion Blame');
  }

  public function getSettingDefaultValue() {
    return false;
  }

}
