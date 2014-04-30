<?php

final class PhabricatorDashboardRenderingEngine extends Phobject {

  private $dashboard;
  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function setDashboard(PhabricatorDashboard $dashboard) {
    $this->dashboard = $dashboard;
    return $this;
  }

  public function renderDashboard() {
    $dashboard = $this->dashboard;
    $viewer = $this->viewer;

    $result = array();
    foreach ($dashboard->getPanels() as $panel) {
      $result[] = id(new PhabricatorDashboardPanelRenderingEngine())
        ->setViewer($viewer)
        ->setPanel($panel)
        ->setEnableAsyncRendering(true)
        ->renderPanel();
    }

    return $result;
  }

}
