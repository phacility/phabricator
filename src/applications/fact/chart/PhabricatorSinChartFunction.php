<?php

final class PhabricatorSinChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'sin';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('x')
        ->setType('function'),
    );
  }

  protected function assignArguments(array $arguments) {
    $this->argument = $arguments[0];
  }

  public function getDatapoints(PhabricatorChartDataQuery $query) {
    $points = $this->getArgument('x')->getDatapoints($query);

    foreach ($points as $key => $point) {
      $points[$key]['y'] = sin(deg2rad($points[$key]['y']));
    }

    return $points;
  }

  public function hasDomain() {
    return false;
  }

}
