<?php

final class PhabricatorMinChartFunction
  extends PhabricatorPureChartFunction {

  const FUNCTIONKEY = 'min';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('min')
        ->setType('number'),
    );
  }

  public function evaluateFunction(array $xv) {
    $min = $this->getArgument('min');

    $yv = array();
    foreach ($xv as $x) {
      if ($x < $min) {
        $yv[] = null;
      } else {
        $yv[] = $x;
      }
    }

    return $yv;
  }

}
