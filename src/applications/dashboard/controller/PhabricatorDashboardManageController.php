<?php

final class PhabricatorDashboardManageController
  extends PhabricatorDashboardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $dashboard_uri = $this->getApplicationURI('view/'.$id.'/');

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

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $dashboard,
      PhabricatorPolicyCapability::CAN_EDIT);

    $title = $dashboard->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Dashboard %d', $dashboard->getID()),
      $dashboard_uri);
    $crumbs->addTextCrumb(pht('Manage'));
    $crumbs->setBorder(true);

    $header = $this->buildHeaderView($dashboard);
    $curtain = $this->buildCurtainview($dashboard);
    $properties = $this->buildPropertyView($dashboard);

    $timeline = $this->buildTransactionTimeline(
      $dashboard,
      new PhabricatorDashboardTransactionQuery());

    $info_view = null;
    if (!$can_edit) {
      $no_edit = pht(
        'You do not have permission to edit this dashboard. If you want to '.
        'make changes, make a copy first.');

      $info_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->setErrors(array($no_edit));
    }

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
      ->setMainColumn(array(
        $info_view,
        $properties,
        $timeline,
      ))
      ->setFooter($dashboard_box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function buildHeaderView(PhabricatorDashboard $dashboard) {
    $viewer = $this->getViewer();
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

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View Dashboard'))
      ->setIcon('fa-columns')
      ->setHref($this->getApplicationURI("view/{$id}/"));

    return id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($dashboard->getName())
      ->setPolicyObject($dashboard)
      ->setStatus($status_icon, $status_color, $status_name)
      ->setHeaderIcon($dashboard->getIcon())
      ->addActionLink($button);
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

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Copy Dashboard'))
        ->setIcon('fa-files-o')
        ->setHref($this->getApplicationURI("copy/{$id}/"))
        ->setWorkflow(true));

    $installed_dashboard = id(new PhabricatorDashboardInstall())
      ->loadOneWhere(
        'objectPHID = %s AND applicationClass = %s',
        $viewer->getPHID(),
        'PhabricatorHomeApplication');
    if ($installed_dashboard &&
        $installed_dashboard->getDashboardPHID() == $dashboard->getPHID()) {
      $title_install = pht('Uninstall Dashboard');
      $href_install = "uninstall/{$id}/";
    } else {
      $title_install = pht('Install Dashboard');
      $href_install = "install/{$id}/";
    }
    $curtain->addAction(
      id(new PhabricatorActionView())
      ->setName($title_install)
      ->setIcon('fa-wrench')
      ->setHref($this->getApplicationURI($href_install))
      ->setWorkflow(true));

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
