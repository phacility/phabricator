<?php

final class PhabricatorProjectBurndownChartEngine
  extends PhabricatorChartEngine {

  const CHARTENGINEKEY = 'project.burndown';

  public function setProjects(array $projects) {
    assert_instances_of($projects, 'PhabricatorProject');
    $project_phids = mpull($projects, 'getPHID');
    return $this->setEngineParameter('projectPHIDs', $project_phids);
  }

  protected function newChart(PhabricatorFactChart $chart, array $map) {
    $viewer = $this->getViewer();

    $map = $map + array(
      'projectPHIDs' => array(),
    );

    if ($map['projectPHIDs']) {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withPHIDs($map['projectPHIDs'])
        ->execute();
      $project_phids = mpull($projects, 'getPHID');
    } else {
      $project_phids = array();
    }

    $functions = array();
    if ($project_phids) {
      foreach ($project_phids as $project_phid) {
        $function = $this->newFunction(
          array(
            'accumulate',
            array(
              'compose',
              array('fact', 'tasks.open-count.assign.project', $project_phid),
              array('min', 0),
            ),
          ));

        $function->getFunctionLabel()
          ->setName(pht('Tasks Moved Into Project'))
          ->setColor('rgba(128, 128, 200, 1)')
          ->setFillColor('rgba(128, 128, 200, 0.15)');

        $functions[] = $function;

        $function = $this->newFunction(
          array(
            'accumulate',
            array('fact', 'tasks.open-count.create.project', $project_phid),
          ));

        $function->getFunctionLabel()
          ->setName(pht('Tasks Created'))
          ->setColor('rgba(0, 0, 200, 1)')
          ->setFillColor('rgba(0, 0, 200, 0.15)');

        $functions[] = $function;

        $function = $this->newFunction(
          array(
            'accumulate',
            array(
              'compose',
              array('fact', 'tasks.open-count.assign.project', $project_phid),
              array('max', 0),
            ),
          ));

        $function->getFunctionLabel()
          ->setName(pht('Tasks Moved Out of Project'))
          ->setColor('rgba(128, 200, 128, 1)')
          ->setFillColor('rgba(128, 200, 128, 0.15)');

        $functions[] = $function;

        $function = $this->newFunction(
          array(
            'accumulate',
            array('fact', 'tasks.open-count.status.project', $project_phid),
          ));

        $function->getFunctionLabel()
          ->setName(pht('Tasks Closed'))
          ->setColor('rgba(0, 200, 0, 1)')
          ->setFillColor('rgba(0, 200, 0, 0.15)');

        $functions[] = $function;
      }
    } else {
      $function = $this->newFunction(
        array(
          'accumulate',
          array('fact', 'tasks.open-count.create'),
        ));

      $function->getFunctionLabel()
        ->setName(pht('Tasks Created'))
        ->setColor('rgba(0, 0, 200, 1)')
        ->setFillColor('rgba(0, 0, 200, 0.15)');

      $functions[] = $function;

      $function = $this->newFunction(
        array(
          'accumulate',
          array('fact', 'tasks.open-count.status'),
        ));

      $function->getFunctionLabel()
        ->setName(pht('Tasks Closed'))
        ->setColor('rgba(0, 200, 0, 1)')
        ->setFillColor('rgba(0, 200, 0, 0.15)');

      $functions[] = $function;
    }

    $datasets = array();

    $datasets[] = id(new PhabricatorChartStackedAreaDataset())
      ->setFunctions($functions);

    $chart->attachDatasets($datasets);
  }

}
