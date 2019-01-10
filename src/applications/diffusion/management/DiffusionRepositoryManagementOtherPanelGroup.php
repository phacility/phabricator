<?php

final class DiffusionRepositoryManagementOtherPanelGroup
  extends DiffusionRepositoryManagementPanelGroup {

  const PANELGROUPKEY = 'other';

  public function getManagementPanelGroupLabel() {
    return pht('Other');
  }

  public function getManagementPanelGroupOrder() {
    return 9999;
  }

}
