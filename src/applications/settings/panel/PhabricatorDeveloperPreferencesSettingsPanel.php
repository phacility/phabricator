<?php

final class PhabricatorDeveloperPreferencesSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'developer';

  public function getPanelName() {
    return pht('Developer Settings');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsDeveloperPanelGroup::PANELGROUPKEY;
  }

  public function isTemplatePanel() {
    return true;
  }

}
