<?php

final class PhabricatorEmailFormatSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'emailformat';

  public function getPanelName() {
    return pht('Email Format');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsEmailPanelGroup::PANELGROUPKEY;
  }

  public function isManagementPanel() {
    if ($this->getUser()->getIsMailingList()) {
      return true;
    }

    return false;
  }

  public function isTemplatePanel() {
    return true;
  }

}
