<?php

final class PhabricatorSettingsDeveloperPanelGroup
  extends PhabricatorSettingsPanelGroup {

  const PANELGROUPKEY = 'developer';

  public function getPanelGroupName() {
    return pht('Developer');
  }

  protected function getPanelGroupOrder() {
    return 400;
  }

}
