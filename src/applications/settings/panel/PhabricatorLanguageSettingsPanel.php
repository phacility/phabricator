<?php

final class PhabricatorLanguageSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'language';

  public function getPanelName() {
    return pht('Language');
  }

  public function getPanelMenuIcon() {
    return 'fa-globe';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsAccountPanelGroup::PANELGROUPKEY;
  }

  public function isManagementPanel() {
    return true;
  }

  public function isTemplatePanel() {
    return true;
  }

  public function isMultiFactorEnrollmentPanel() {
    return true;
  }

}
