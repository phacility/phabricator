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
          'accumulate',
          array('fact', 'tasks.open-count.create.project', $project_phid),
        );
        $argvs[] = array(
          'accumulate',
          array('fact', 'tasks.open-count.status.project', $project_phid),
        );
        $argvs[] = array(
          'accumulate',
          array('fact', 'tasks.open-count.assign.project', $project_phid),
        );
      }
    } else {
      $argvs[] = array('accumulate', array('fact', 'tasks.open-count.create'));
      $argvs[] = array('accumulate', array('fact', 'tasks.open-count.status'));
    }

    $functions = array();
    foreach ($argvs as $argv) {
      $functions[] = id(new PhabricatorComposeChartFunction())
        ->setArguments(array($argv));
    }

    $datasets = array();

    $datasets[] = id(new PhabricatorChartStackedAreaDataset())
      ->setFunctions($functions);

    $chart = id(new PhabricatorFactChart())
      ->setDatasets($datasets);

    return $chart;
  }

}
