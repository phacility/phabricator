<?php

final class PhabricatorDashboardArrangeController
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
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }

    $title = $dashboard->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Dashboard %d', $dashboard->getID()),
      $this->getApplicationURI('view/'.$dashboard->getID().'/'));
    $crumbs->addTextCrumb(pht('Arrange'));

    $rendered_dashboard = id(new PhabricatorDashboardRenderingEngine())
      ->setViewer($viewer)
      ->setDashboard($dashboard)
      ->setArrangeMode(true)
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

}
