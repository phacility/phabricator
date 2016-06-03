<?php

final class PhabricatorDateTimeSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'datetime';

  public function getPanelName() {
    return pht('Date and Time');
  }

  public function getPanelGroup() {
    return pht('Account Information');
  }

  public function isEditableByAdministrators() {
    return true;
  }

}
