<?php

final class DiffusionRepositoryClusterManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'cluster';

  public function getManagementPanelLabel() {
    return pht('Cluster Configuration');
  }

  public function getManagementPanelOrder() {
    return 12345;
  }

  public function buildManagementPanelContent() {
    return pht('TODO: Cluster configuration management.');
  }

}
