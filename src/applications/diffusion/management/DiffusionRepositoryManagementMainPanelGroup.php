<?php

final class DiffusionRepositoryManagementMainPanelGroup
  extends DiffusionRepositoryManagementPanelGroup {

  const PANELGROUPKEY = 'main';

  public function getManagementPanelGroupLabel() {
    return null;
  }

  public function getManagementPanelGroupOrder() {
    return 1000;
  }

}
