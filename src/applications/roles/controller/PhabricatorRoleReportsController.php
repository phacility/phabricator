<?php

final class PhabricatorRoleReportsController
  extends PhabricatorRoleController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadRole();
    if ($response) {
      return $response;
    }

    $role = $this->getRole();
    $id = $role->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_EDIT);

    $nav = $this->newNavigation(
      $role,
      PhabricatorRole::ITEM_REPORTS);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Reports'));
    $crumbs->setBorder(true);

    $chart_panel = id(new PhabricatorRoleBurndownChartEngine())
      ->setViewer($viewer)
      ->setRoles(array($role))
      ->buildChartPanel();

    $chart_panel->setName(pht('%s: Burndown', $role->getName()));

    $chart_view = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($chart_panel)
      ->setParentPanelPHIDs(array())
      ->renderPanel();

    $activity_panel = id(new PhabricatorRoleActivityChartEngine())
      ->setViewer($viewer)
      ->setRoles(array($role))
      ->buildChartPanel();

    $activity_panel->setName(pht('%s: Activity', $role->getName()));

    $activity_view = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($activity_panel)
      ->setParentPanelPHIDs(array())
      ->renderPanel();

    $view = id(new PHUITwoColumnView())
      ->setFooter(
        array(
          $chart_view,
          $activity_view,
        ));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($role->getName(), pht('Reports')))
      ->appendChild($view);
  }

}
