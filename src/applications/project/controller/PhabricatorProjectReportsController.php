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

    $project_phid = $project->getPHID();

    $argv = array(
      'sum',
      array(
        'accumulate',
        array('fact', 'tasks.open-count.create.project', $project_phid),
      ),
      array(
        'accumulate',
        array('fact', 'tasks.open-count.status.project', $project_phid),
      ),
      array(
        'accumulate',
        array('fact', 'tasks.open-count.assign.project', $project_phid),
      ),
    );

    $function = id(new PhabricatorComposeChartFunction())
      ->setArguments(array($argv));

    $datasets = array(
      id(new PhabricatorChartDataset())
        ->setFunction($function),
    );

    $chart = id(new PhabricatorFactChart())
      ->setDatasets($datasets);

    $engine = id(new PhabricatorChartEngine())
      ->setViewer($viewer)
      ->setChart($chart);

    $chart = $engine->getStoredChart();

    $panel_type = id(new PhabricatorDashboardChartPanelType())
      ->getPanelTypeKey();

    $chart_panel = id(new PhabricatorDashboardPanel())
      ->setPanelType($panel_type)
      ->setName(pht('%s: Burndown', $project->getName()))
      ->setProperty('chartKey', $chart->getChartKey());

    $chart_view = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($chart_panel)
      ->setParentPanelPHIDs(array())
      ->renderPanel();

    $view = id(new PHUITwoColumnView())
      ->setFooter(
        array(
          $chart_view,
        ));

    return $this->newPage()
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->setTitle(array($project->getName(), pht('Reports')))
      ->appendChild($view);
  }

}
