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

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
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
    $dashboard = $this->getDashboard();
    $id = $dashboard->getID();
    $dashboard_uri = $this->getApplicationURI("/view/{$id}/");

    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb($dashboard->getName(), $dashboard_uri);
    $crumbs->setBorder(true);
    return $crumbs;
  }

  protected function buildSideNavView($filter = null) {
    $viewer = $this->getViewer();
    $dashboard = $this->getDashboard();
    $id = $dashboard->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $dashboard,
      PhabricatorPolicyCapability::CAN_EDIT);

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel(pht('Dashboard'));

    $nav->addFilter(
      'view',
      pht('View Dashboard'),
      $this->getApplicationURI("/view/{$id}/"),
      'fa-dashboard');

    $nav->addFilter(
      'arrange',
      pht('Arrange Panels'),
      $this->getApplicationURI("/arrange/{$id}/"),
      'fa-columns');

    $nav->addFilter(
      'manage',
      pht('Manage Dashboard'),
      $this->getApplicationURI("/manage/{$id}/"),
      'fa-gears');

    $nav->selectFilter($filter);

    return $nav;
  }

}
