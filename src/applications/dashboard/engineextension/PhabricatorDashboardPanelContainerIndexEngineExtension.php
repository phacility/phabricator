<?php

final class PhabricatorDashboardPanelContainerIndexEngineExtension
  extends PhabricatorEdgeIndexEngineExtension {

  const EXTENSIONKEY = 'dashboard.panel.container';

  public function getExtensionName() {
    return pht('Dashboard Panel Containers');
  }

  public function shouldIndexObject($object) {
    if (!($object instanceof PhabricatorDashboardPanelContainerInterface)) {
      return false;
    }

    return true;
  }

  protected function getIndexEdgeType() {
    return PhabricatorObjectUsesDashboardPanelEdgeType::EDGECONST;
  }

  protected function getIndexDestinationPHIDs($object) {
    return $object->getDashboardPanelContainerPanelPHIDs();
  }

}
