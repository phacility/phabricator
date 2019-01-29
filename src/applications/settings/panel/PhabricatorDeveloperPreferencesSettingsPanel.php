<?php

final class PhabricatorDeveloperPreferencesSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'developer';

  public function getPanelName() {
    return pht('Developer Settings');
  }

  public function getPanelMenuIcon() {
    return 'fa-magic';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsDeveloperPanelGroup::PANELGROUPKEY;
  }

  public function isTemplatePanel() {
    return true;
  }

}
