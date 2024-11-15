<?php

final class PhabricatorRoleActivityChartEngine
  extends PhabricatorChartEngine {

  const CHARTENGINEKEY = 'role.activity';

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

    $role_phid = head($role_phids);

    $functions = array();
    $stacks = array();

    $function = $this->newFunction(
      array(
        'accumulate',
        array(
          'compose',
          array('fact', 'tasks.open-count.assign.role', $role_phid),
          array('min', 0),
        ),
      ));

    $function->getFunctionLabel()
      ->setKey('moved-in')
      ->setName(pht('Tasks Moved Into Role'))
      ->setColor('rgba(128, 128, 200, 1)')
      ->setFillColor('rgba(128, 128, 200, 0.15)');

    $functions[] = $function;

    $function = $this->newFunction(
      array(
        'accumulate',
        array(
          'compose',
          array('fact', 'tasks.open-count.status.role', $role_phid),
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
        array('fact', 'tasks.open-count.create.role', $role_phid),
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
          array('fact', 'tasks.open-count.status.role', $role_phid),
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
          array('fact', 'tasks.open-count.assign.role', $role_phid),
          array('max', 0),
        ),
      ));

    $function->getFunctionLabel()
      ->setKey('moved-out')
      ->setName(pht('Tasks Moved Out of Role'))
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
