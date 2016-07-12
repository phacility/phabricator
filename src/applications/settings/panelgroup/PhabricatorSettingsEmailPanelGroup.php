<?php

final class PhabricatorSettingsEmailPanelGroup
  extends PhabricatorSettingsPanelGroup {

  const PANELGROUPKEY = 'email';

  public function getPanelGroupName() {
    return pht('Email');
  }

  protected function getPanelGroupOrder() {
    return 500;
  }

}
