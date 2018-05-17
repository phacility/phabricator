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

  public function isUserPanel() {
    return PhabricatorMetaMTAMail::shouldMailEachRecipient();
  }

  public function isManagementPanel() {
    return false;
/*
        if (!$this->isUserPanel()) {
      return false;
    }

    if ($this->getUser()->getIsMailingList()) {
      return true;
    }

    return false;
*/
  }

  public function isTemplatePanel() {
    return true;
  }

}
