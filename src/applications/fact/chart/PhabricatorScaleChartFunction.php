<?php

final class PhabricatorScaleChartFunction
  extends PhabricatorPureChartFunction {

  const FUNCTIONKEY = 'scale';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('scale')
        ->setType('number'),
    );
  }

  public function evaluateFunction(array $xv) {
    $scale = $this->getArgument('scale');

    $yv = array();

    foreach ($xv as $x) {
      $yv[] = $x * $scale;
    }

    return $yv;
  }

}
