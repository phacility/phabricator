<?php

final class PhabricatorSettingsAccountPanelGroup
  extends PhabricatorSettingsPanelGroup {

  const PANELGROUPKEY = 'account';

  public function getPanelGroupName() {
    return null;
  }

  protected function getPanelGroupOrder() {
    return 100;
  }

}
