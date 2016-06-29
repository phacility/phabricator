<?php

final class PhabricatorSettingsAccountPanelGroup
  extends PhabricatorSettingsPanelGroup {

  const PANELGROUPKEY = 'account';

  public function getPanelGroupName() {
    return pht('Account');
  }

  protected function getPanelGroupOrder() {
    return 100;
  }

}
