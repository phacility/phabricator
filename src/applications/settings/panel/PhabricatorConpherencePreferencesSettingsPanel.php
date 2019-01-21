<?php

final class PhabricatorConpherencePreferencesSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'conpherence';

  public function getPanelName() {
    return pht('Conpherence');
  }

  public function getPanelMenuIcon() {
    return 'fa-comment-o';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsApplicationsPanelGroup::PANELGROUPKEY;
  }

  public function isTemplatePanel() {
    return true;
  }

}
