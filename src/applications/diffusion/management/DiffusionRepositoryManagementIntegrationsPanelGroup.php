<?php

final class DiffusionRepositoryManagementIntegrationsPanelGroup
  extends DiffusionRepositoryManagementPanelGroup {

  const PANELGROUPKEY = 'integrations';

  public function getManagementPanelGroupLabel() {
    return pht('Integrations');
  }

  public function getManagementPanelGroupOrder() {
    return 4000;
  }

}
