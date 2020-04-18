<?php

final class PhabricatorExternalEditorSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'editor';

  public function getPanelName() {
    return pht('External Editor');
  }

  public function getPanelMenuIcon() {
    return 'fa-i-cursor';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsApplicationsPanelGroup::PANELGROUPKEY;
  }

  public function isTemplatePanel() {
    return true;
  }

}
