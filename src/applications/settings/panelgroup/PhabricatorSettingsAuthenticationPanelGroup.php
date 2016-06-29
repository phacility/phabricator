<?php

final class PhabricatorSettingsAuthenticationPanelGroup
  extends PhabricatorSettingsPanelGroup {

  const PANELGROUPKEY = 'authentication';

  public function getPanelGroupName() {
    return pht('Authentication');
  }

  protected function getPanelGroupOrder() {
    return 300;
  }

}
