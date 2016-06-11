<?php

final class PhabricatorConpherencePreferencesSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'conpherence';

  public function getPanelName() {
    return pht('Conpherence Preferences');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsApplicationsPanelGroup::PANELGROUPKEY;
  }

  public function isTemplatePanel() {
    return true;
  }

}
