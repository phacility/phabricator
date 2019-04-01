<?php

final class PhabricatorDashboardViewController
  extends PhabricatorDashboardProfileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needPanels(true)
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }
    $this->setDashboard($dashboard);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $dashboard,
      PhabricatorPolicyCapability::CAN_EDIT);

    $title = $dashboard->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $header = $this->buildHeaderView();

    $curtain = $this->buildCurtainView($dashboard);

    $timeline = $this->buildTransactionTimeline(
      $dashboard,
      new PhabricatorDashboardTransactionQuery());
    $timeline->setShouldTerminate(true);

    $rendered_dashboard = id(new PhabricatorDashboardRenderingEngine())
      ->setViewer($viewer)
      ->setDashboard($dashboard)
      ->setArrangeMode($can_edit)
      ->renderDashboard();

    $dashboard_box = id(new PHUIBoxView())
      ->addClass('dashboard-preview-box')
      ->appendChild($rendered_dashboard);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $dashboard_box,
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function buildCurtainView(PhabricatorDashboard $dashboard) {
    $viewer = $this->getViewer();
    $id = $dashboard->getID();

    $curtain = $this->newCurtainView($dashboard);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $dashboard,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Dashboard'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Add Dashboard to Menu'))
        ->setIcon('fa-wrench')
        ->setHref($this->getApplicationURI("/install/{$id}/"))
        ->setWorkflow(true));

    if ($dashboard->isArchived()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Dashboard'))
          ->setIcon('fa-check')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow($can_edit));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Dashboard'))
          ->setIcon('fa-ban')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow($can_edit));
    }

    return $curtain;
  }


}
