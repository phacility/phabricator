<?php

final class DiffusionRepositoryManagementBuildsPanelGroup
  extends DiffusionRepositoryManagementPanelGroup {

  const PANELGROUPKEY = 'builds';

  public function getManagementPanelGroupLabel() {
    return pht('Builds');
  }

  public function getManagementPanelGroupOrder() {
    return 2000;
  }

}
