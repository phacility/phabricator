<?php

final class PhabricatorConstantChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'constant';

  private $value;

  protected function newArguments(array $arguments) {
    if (count($arguments) !== 1) {
      throw new Exception(
        pht(
          'Chart function "constant(...)" expects one argument, got %s. '.
          'Pass a constant.',
          count($arguments)));
    }

    if (!is_int($arguments[0])) {
      throw new Exception(
        pht(
          'First argument for "fact(...)" is invalid: expected int, '.
          'got %s.',
          phutil_describe_type($arguments[0])));
    }

    $this->value = $arguments[0];
  }

  public function getDatapoints(PhabricatorChartDataQuery $query) {
    $x_min = $query->getMinimumValue();
    $x_max = $query->getMaximumValue();

    $points = array();
    $steps = $this->newLinearSteps($x_min, $x_max, 2);
    foreach ($steps as $step) {
      $points[] = array(
        'x' => $step,
        'y' => $this->value,
      );
    }

    return $points;
  }

  public function hasDomain() {
    return false;
  }

}
