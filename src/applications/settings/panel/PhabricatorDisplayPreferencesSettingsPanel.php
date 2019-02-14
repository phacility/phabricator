<?php

final class PhabricatorDisplayPreferencesSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'display';

  public function getPanelName() {
    return pht('Display Preferences');
  }

  public function getPanelMenuIcon() {
    return 'fa-desktop';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsApplicationsPanelGroup::PANELGROUPKEY;
  }

  public function isTemplatePanel() {
    return true;
  }

}
