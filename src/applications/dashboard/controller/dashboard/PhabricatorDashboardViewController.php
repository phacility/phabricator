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

    $usage_box = $this->newUsageView($dashboard);

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
          $usage_box,
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
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

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
          ->setWorkflow(true));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Dashboard'))
          ->setIcon('fa-ban')
          ->setHref($this->getApplicationURI("archive/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    return $curtain;
  }

  private function newUsageView(PhabricatorDashboard $dashboard) {
    $viewer = $this->getViewer();

    $custom_phids = array();
    if ($viewer->getPHID()) {
      $custom_phids[] = $viewer->getPHID();
    }

    $items = id(new PhabricatorProfileMenuItemConfigurationQuery())
      ->setViewer($viewer)
      ->withAffectedObjectPHIDs(
        array(
          $dashboard->getPHID(),
        ))
      ->withCustomPHIDs($custom_phids, $include_global = true)
      ->execute();

    $handle_phids = array();
    foreach ($items as $item) {
      $handle_phids[] = $item->getProfilePHID();
      $custom_phid = $item->getCustomPHID();
      if ($custom_phid) {
        $handle_phids[] = $custom_phid;
      }
    }

    if ($handle_phids) {
      $handles = $viewer->loadHandles($handle_phids);
    } else {
      $handles = array();
    }

    $items = msortv($items, 'newUsageSortVector');

    $rows = array();
    foreach ($items as $item) {
      $profile_phid = $item->getProfilePHID();
      $custom_phid = $item->getCustomPHID();

      $profile = $handles[$profile_phid]->renderLink();
      $profile_icon = $handles[$profile_phid]->getIcon();

      if ($custom_phid) {
        $custom = $handles[$custom_phid]->renderLink();
      } else {
        $custom = pht('Global');
      }

      $type = $item->getProfileMenuTypeDescription();

      $rows[] = array(
        id(new PHUIIconView())->setIcon($profile_icon),
        $type,
        $profile,
        $custom,
      );
    }

    $usage_table = id(new AphrontTableView($rows))
      ->setNoDataString(
        pht('This dashboard has not been added to any menus.'))
      ->setHeaders(
        array(
          null,
          pht('Type'),
          pht('Menu'),
          pht('Global/Personal'),
        ))
      ->setColumnClasses(
        array(
          'center',
          null,
          'pri',
          'wide',
        ));

    $header_view = id(new PHUIHeaderView())
      ->setHeader(pht('Dashboard Used By'));

    $usage_box = id(new PHUIObjectBoxView())
      ->setTable($usage_table)
      ->setHeader($header_view);

    return $usage_box;
  }


}
