<?php

abstract class PhabricatorDashboardProfileController
  extends PhabricatorController {

  private $dashboard;

  public function setDashboard(PhabricatorDashboard $dashboard) {
    $this->dashboard = $dashboard;
    return $this;
  }

  public function getDashboard() {
    return $this->dashboard;
  }

  protected function buildHeaderView() {
    $viewer = $this->getViewer();
    $dashboard = $this->getDashboard();
    $id = $dashboard->getID();

    if ($dashboard->isArchived()) {
      $status_icon = 'fa-ban';
      $status_color = 'dark';
    } else {
      $status_icon = 'fa-check';
      $status_color = 'bluegrey';
    }

    $status_name = idx(
      PhabricatorDashboard::getStatusNameMap(),
      $dashboard->getStatus());

    return id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($dashboard->getName())
      ->setPolicyObject($dashboard)
      ->setStatus($status_icon, $status_color, $status_name)
      ->setHeaderIcon($dashboard->getIcon());
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->setBorder(true);

    $dashboard = $this->getDashboard();
    if ($dashboard) {
      $crumbs->addTextCrumb($dashboard->getName(), $dashboard->getURI());
    }

    return $crumbs;
  }

}
