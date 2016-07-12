<?php

final class PhabricatorDiffusionColorSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'diffusion-color';

  public function getSettingName() {
    return pht('Diffusion Color');
  }

  public function getSettingDefaultValue() {
    return false;
  }

}
