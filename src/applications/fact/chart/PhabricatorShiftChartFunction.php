<?php

final class PhabricatorShiftChartFunction
  extends PhabricatorPureChartFunction {

  const FUNCTIONKEY = 'shift';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('shift')
        ->setType('number'),
    );
  }

  public function evaluateFunction(array $xv) {
    $shift = $this->getArgument('shift');

    $yv = array();

    foreach ($xv as $x) {
      $yv[] = $x + $shift;
    }

    return $yv;
  }

}
