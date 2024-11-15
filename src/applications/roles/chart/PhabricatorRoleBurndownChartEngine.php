<?php

final class PhabricatorRoleBurndownChartEngine
  extends PhabricatorChartEngine {

  const CHARTENGINEKEY = 'role.burndown';

  public function setRoles(array $roles) {
    assert_instances_of($roles, 'PhabricatorRole');
    $role_phids = mpull($roles, 'getPHID');
    return $this->setEngineParameter('rolePHIDs', $role_phids);
  }

  protected function newChart(PhabricatorFactChart $chart, array $map) {
    $viewer = $this->getViewer();

    $map = $map + array(
      'rolePHIDs' => array(),
    );

    if ($map['rolePHIDs']) {
      $roles = id(new PhabricatorRoleQuery())
        ->setViewer($viewer)
        ->withPHIDs($map['rolePHIDs'])
        ->execute();
      $role_phids = mpull($roles, 'getPHID');
    } else {
      $role_phids = array();
    }

    $functions = array();
    if ($role_phids) {
      $open_function = $this->newFunction(
        array(
          'accumulate',
          array(
            'sum',
            $this->newFactSum(
              'tasks.open-count.create.role', $role_phids),
            $this->newFactSum(
              'tasks.open-count.status.role', $role_phids),
            $this->newFactSum(
              'tasks.open-count.assign.role', $role_phids),
          ),
        ));

      $closed_function = $this->newFunction(
        array(
          'accumulate',
          $this->newFactSum('tasks.open-count.status.role', $role_phids),
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
