<?php

final class PhabricatorProjectReportsController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();
    $id = $project->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $nav = $this->newNavigation(
      $project,
      PhabricatorProject::ITEM_REPORTS);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Reports'));
    $crumbs->setBorder(true);

    $chart_panel = id(new PhabricatorProjectBurndownChartEngine())
      ->setViewer($viewer)
      ->setProjects(array($project))
      ->buildChartPanel();

    $chart_panel->setName(pht('%s: Burndown', $project->getName()));

    $chart_view = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($chart_panel)
      ->setParentPanelPHIDs(array())
      ->renderPanel();

    $activity_panel = id(new PhabricatorProjectActivityChartEngine())
      ->setViewer($viewer)
      ->setProjects(array($project))
      ->buildChartPanel();

    $activity_panel->setName(pht('%s: Activity', $project->getName()));

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
      ->setTitle(array($project->getName(), pht('Reports')))
      ->appendChild($view);
  }

}
