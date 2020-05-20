<?php

final class PhabricatorSearchSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'search';

  public function getPanelName() {
    return pht('Search');
  }

  public function getPanelMenuIcon() {
    return 'fa-search';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsApplicationsPanelGroup::PANELGROUPKEY;
  }

  public function isTemplatePanel() {
    return true;
  }

  public function isUserPanel() {
    return false;
  }

}
