<?php

final class PhabricatorProjectActivityChartEngine
  extends PhabricatorChartEngine {

  const CHARTENGINEKEY = 'project.activity';

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

    $project_phid = head($project_phids);

    $functions = array();
    $stacks = array();

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
      ->setKey('moved-in')
      ->setName(pht('Tasks Moved Into Project'))
      ->setColor('rgba(128, 128, 200, 1)')
      ->setFillColor('rgba(128, 128, 200, 0.15)');

    $functions[] = $function;

    $function = $this->newFunction(
      array(
        'accumulate',
        array(
          'compose',
          array('fact', 'tasks.open-count.status.project', $project_phid),
          array('min', 0),
        ),
      ));

    $function->getFunctionLabel()
      ->setKey('reopened')
      ->setName(pht('Tasks Reopened'))
      ->setColor('rgba(128, 128, 200, 1)')
      ->setFillColor('rgba(128, 128, 200, 0.15)');

    $functions[] = $function;

    $function = $this->newFunction(
      array(
        'accumulate',
        array('fact', 'tasks.open-count.create.project', $project_phid),
      ));

    $function->getFunctionLabel()
      ->setKey('created')
      ->setName(pht('Tasks Created'))
      ->setColor('rgba(0, 0, 200, 1)')
      ->setFillColor('rgba(0, 0, 200, 0.15)');

    $functions[] = $function;

    $function = $this->newFunction(
      array(
        'accumulate',
        array(
          'compose',
          array('fact', 'tasks.open-count.status.project', $project_phid),
          array('max', 0),
        ),
      ));

    $function->getFunctionLabel()
      ->setKey('closed')
      ->setName(pht('Tasks Closed'))
      ->setColor('rgba(0, 200, 0, 1)')
      ->setFillColor('rgba(0, 200, 0, 0.15)');

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
      ->setKey('moved-out')
      ->setName(pht('Tasks Moved Out of Project'))
      ->setColor('rgba(128, 200, 128, 1)')
      ->setFillColor('rgba(128, 200, 128, 0.15)');

    $functions[] = $function;

    $stacks[] = array('created', 'reopened', 'moved-in');
    $stacks[] = array('closed', 'moved-out');

    $datasets = array();

    $dataset = id(new PhabricatorChartStackedAreaDataset())
      ->setFunctions($functions)
      ->setStacks($stacks);

    $datasets[] = $dataset;
    $chart->attachDatasets($datasets);
  }

}
