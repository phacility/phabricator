<?php

final class PhabricatorCosChartFunction
  extends PhabricatorPureChartFunction {

  const FUNCTIONKEY = 'cos';

  protected function newArguments() {
    return array();
  }

  public function evaluateFunction(array $xv) {
    $yv = array();

    foreach ($xv as $x) {
      $yv[] = cos(deg2rad($x));
    }

    return $yv;
  }

}
