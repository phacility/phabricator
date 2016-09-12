<?php

final class PhabricatorConpherenceWidgetVisibleSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'conpherence-widget';

  public function getSettingName() {
    return pht('Conpherence Widget Pane Visible');
  }

}
