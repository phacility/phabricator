<?php

final class PhabricatorDiffPreferencesSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'diff';

  public function getPanelName() {
    return pht('Diff Preferences');
  }

  public function getPanelMenuIcon() {
    return 'fa-cog';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsApplicationsPanelGroup::PANELGROUPKEY;
  }

  public function isTemplatePanel() {
    return true;
  }

}
