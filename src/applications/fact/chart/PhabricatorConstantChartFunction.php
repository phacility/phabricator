<?php

final class PhabricatorConstantChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'constant';

  private $value;

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('n')
        ->setType('number'),
    );
  }

  public function getDatapoints(PhabricatorChartDataQuery $query) {
    $x_min = $query->getMinimumValue();
    $x_max = $query->getMaximumValue();

    $value = $this->getArgument('n');

    $points = array();
    $steps = $this->newLinearSteps($x_min, $x_max, 2);
    foreach ($steps as $step) {
      $points[] = array(
        'x' => $step,
        'y' => $value,
      );
    }

    return $points;
  }

  public function hasDomain() {
    return false;
  }

}
