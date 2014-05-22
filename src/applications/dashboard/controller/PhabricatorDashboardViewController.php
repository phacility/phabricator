<?php

final class PhabricatorDashboardViewController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needPanels(true)
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }

    $title = $dashboard->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Dashboard %d', $dashboard->getID()));

    $rendered_dashboard = id(new PhabricatorDashboardRenderingEngine())
      ->setViewer($viewer)
      ->setDashboard($dashboard)
      ->renderDashboard();

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $rendered_dashboard,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $id = $this->id;

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setIcon('fa-th')
        ->setName(pht('Manage Dashboard'))
        ->setHref($this->getApplicationURI()."manage/{$id}/"));

    return $crumbs;
  }

}
