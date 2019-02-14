<?php

final class PhabricatorEmailFormatSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'emailformat';

  public function getPanelName() {
    return pht('Email Format');
  }

  public function getPanelMenuIcon() {
    return 'fa-font';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsEmailPanelGroup::PANELGROUPKEY;
  }

  public function isUserPanel() {
    return PhabricatorMetaMTAMail::shouldMailEachRecipient();
  }

  public function isManagementPanel() {
    return false;
  }

  public function isTemplatePanel() {
    return true;
  }

}
