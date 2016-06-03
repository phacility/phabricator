<?php

final class PhabricatorAccountSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'account';

  public function getPanelName() {
    return pht('Account');
  }

  public function getPanelGroup() {
    return pht('Account Information');
  }

  public function isEditableByAdministrators() {
    return true;
  }

}
