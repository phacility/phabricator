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
      $open_function = $this->newFunction(
        array(
          'accumulate',
          array(
            'sum',
            $this->newFactSum(
              'tasks.open-count.create.project', $project_phids),
            $this->newFactSum(
              'tasks.open-count.status.project', $project_phids),
            $this->newFactSum(
              'tasks.open-count.assign.project', $project_phids),
          ),
        ));

      $closed_function = $this->newFunction(
        array(
          'accumulate',
          $this->newFactSum('tasks.open-count.status.project', $project_phids),
        ));
    } else {
      $open_function = $this->newFunction(
        array(
          'accumulate',
          array(
            'sum',
            array('fact', 'tasks.open-count.create'),
            array('fact', 'tasks.open-count.status'),
          ),
        ));

      $closed_function = $this->newFunction(
        array(
          'accumulate',
          array('fact', 'tasks.open-count.status'),
        ));
    }

    $open_function->getFunctionLabel()
      ->setKey('open')
      ->setName(pht('Open Tasks'))
      ->setColor('rgba(0, 0, 200, 1)')
      ->setFillColor('rgba(0, 0, 200, 0.15)');

    $closed_function->getFunctionLabel()
      ->setKey('closed')
      ->setName(pht('Closed Tasks'))
      ->setColor('rgba(0, 200, 0, 1)')
      ->setFillColor('rgba(0, 200, 0, 0.15)');

    $datasets = array();

    $dataset = id(new PhabricatorChartStackedAreaDataset())
      ->setFunctions(
        array(
          $open_function,
          $closed_function,
        ))
      ->setStacks(
        array(
          array('open'),
          array('closed'),
        ));

    $datasets[] = $dataset;
    $chart->attachDatasets($datasets);
  }

  private function newFactSum($fact_key, array $phids) {
    $result = array();
    $result[] = 'sum';

    foreach ($phids as $phid) {
      $result[] = array('fact', $fact_key, $phid);
    }

    return $result;
  }

}
