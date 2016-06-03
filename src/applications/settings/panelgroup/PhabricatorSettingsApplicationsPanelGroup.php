<?php

final class PhabricatorSettingsApplicationsPanelGroup
  extends PhabricatorSettingsPanelGroup {

  const PANELGROUPKEY = 'applications';

  public function getPanelGroupName() {
    return pht('Applications');
  }

  protected function getPanelGroupOrder() {
    return 200;
  }

}
