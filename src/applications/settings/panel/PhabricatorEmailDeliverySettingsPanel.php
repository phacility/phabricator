<?php

final class PhabricatorEmailDeliverySettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'emaildelivery';

  public function getPanelName() {
    return pht('Email Delivery');
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
