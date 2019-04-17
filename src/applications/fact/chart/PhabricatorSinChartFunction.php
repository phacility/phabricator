<?php

final class PhabricatorSinChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'sin';

  private $argument;

  protected function newArguments(array $arguments) {
    if (count($arguments) !== 1) {
      throw new Exception(
        pht(
          'Chart function "sin(..)" expects one argument, got %s.',
          count($arguments)));
    }

    $argument = $arguments[0];

    if (!($argument instanceof PhabricatorChartFunction)) {
      throw new Exception(
        pht(
          'Argument to chart function should be a function, got %s.',
          phutil_describe_type($argument)));
    }

    $this->argument = $argument;
  }

  public function getDatapoints(PhabricatorChartDataQuery $query) {
    $points = $this->argument->getDatapoints($query);

    foreach ($points as $key => $point) {
      $points[$key]['y'] = sin(deg2rad($points[$key]['y']));
    }

    return $points;
  }

  public function hasDomain() {
    return false;
  }

}
