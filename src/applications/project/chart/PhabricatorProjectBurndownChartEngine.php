<?php

final class PhabricatorProjectBurndownChartEngine
  extends PhabricatorChartEngine {

  const CHARTENGINEKEY = 'project.burndown';

  private $projects;

  public function setProjects(array $projects) {
    assert_instances_of($projects, 'PhabricatorProject');

    $this->projects = $projects;

    return $this;
  }

  public function getProjects() {
    return $this->projects;
  }

  protected function newChart() {
    if ($this->projects !== null) {
      $project_phids = mpull($this->projects, 'getPHID');
    } else {
      $project_phids = null;
    }

    $argvs = array();
    if ($project_phids) {
      foreach ($project_phids as $project_phid) {
        $argvs[] = array(
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
      }
    } else {
      $argvs[] = array(
        'sum',
        array('accumulate', array('fact', 'tasks.open-count.create')),
        array('accumulate', array('fact', 'tasks.open-count.status')),
      );
    }

    $datasets = array();
    foreach ($argvs as $argv) {
      $function = id(new PhabricatorComposeChartFunction())
        ->setArguments(array($argv));

      $datasets[] = id(new PhabricatorChartDataset())
        ->setFunction($function);
    }

    $chart = id(new PhabricatorFactChart())
      ->setDatasets($datasets);

    return $chart;
  }

}
