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

    $header = $this->buildHeaderView($dashboard);
    $actions = $this->buildActionView($dashboard);
    $properties = $this->buildPropertyView($dashboard);

    $properties->setActionList($actions);
    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    if (!$can_edit) {
      $no_edit = pht(
        'You do not have permission to edit this dashboard. If you want to '.
        'make changes, make a copy first.');

      $box->setInfoView(
        id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
          ->setErrors(array($no_edit)));
    }

    $rendered_dashboard = id(new PhabricatorDashboardRenderingEngine())
      ->setViewer($viewer)
      ->setDashboard($dashboard)
      ->setArrangeMode($can_edit)
      ->renderDashboard();

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $rendered_dashboard,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildHeaderView(PhabricatorDashboard $dashboard) {
    $viewer = $this->getRequest()->getUser();

    if ($dashboard->isClosed()) {
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
      ->setStatus($status_icon, $status_color, $status_name);
  }

  private function buildActionView(PhabricatorDashboard $dashboard) {
    $viewer = $this->getRequest()->getUser();
    $id = $dashboard->getID();

    $actions = id(new PhabricatorActionListView())
      ->setObjectURI($this->getApplicationURI('view/'.$dashboard->getID().'/'))
      ->setObject($dashboard)
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $dashboard,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Dashboard'))
        ->setIcon('fa-columns')
        ->setHref($this->getApplicationURI("view/{$id}/")));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Dashboard'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
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
    $actions->addAction(
      id(new PhabricatorActionView())
      ->setName($title_install)
      ->setIcon('fa-wrench')
      ->setHref($this->getApplicationURI($href_install))
      ->setWorkflow(true));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View History'))
        ->setIcon('fa-history')
        ->setHref($this->getApplicationURI("history/{$id}/")));

    return $actions;
  }

  private function buildPropertyView(PhabricatorDashboard $dashboard) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($dashboard);

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $dashboard);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $properties->addProperty(
      pht('Panels'),
      $viewer->renderHandleList($dashboard->getPanelPHIDs()));

    $properties->invokeWillRenderEvent();

    return $properties;
  }

}
