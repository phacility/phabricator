<?php

final class PhabricatorXChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'x';

  protected function newArguments() {
    return array();
  }

  public function getDatapoints(PhabricatorChartDataQuery $query) {
    $x_min = $query->getMinimumValue();
    $x_max = $query->getMaximumValue();
    $limit = $query->getLimit();

    $points = array();
    $steps = $this->newLinearSteps($x_min, $x_max, $limit);
    foreach ($steps as $step) {
      $points[] = array(
        'x' => $step,
        'y' => $step,
      );
    }

    return $points;
  }

  public function hasDomain() {
    return false;
  }

}
