<?php

final class PhabricatorDashboardManageController
  extends PhabricatorDashboardProfileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    // TODO: This UI should drop a lot of capabilities if the user can't
    // edit the dashboard, but we should still let them in for "Install" and
    // "View History".

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
    $crumbs->addTextCrumb(pht('Manage'));

    $header = $this->buildHeaderView();
    $curtain = $this->buildCurtainView($dashboard);
    $properties = $this->buildPropertyView($dashboard);

    $timeline = $this->buildTransactionTimeline(
      $dashboard,
      new PhabricatorDashboardTransactionQuery());
    $timeline->setShouldTerminate(true);

    $info_view = null;
    if (!$can_edit) {
      $no_edit = pht(
        'You do not have permission to edit this dashboard.');

      $info_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->setErrors(array($no_edit));
    }

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $info_view,
        $properties,
        $timeline,
      ));

    $navigation = $this->buildSideNavView('manage');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($navigation)
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

  private function buildPropertyView(PhabricatorDashboard $dashboard) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $dashboard);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $properties->addProperty(
      pht('Panels'),
      $viewer->renderHandleList($dashboard->getPanelPHIDs()));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($properties);
  }

}
