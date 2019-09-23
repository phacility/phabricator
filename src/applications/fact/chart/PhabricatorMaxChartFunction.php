<?php

final class PhabricatorMaxChartFunction
  extends PhabricatorPureChartFunction {

  const FUNCTIONKEY = 'max';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('max')
        ->setType('number'),
    );
  }

  public function evaluateFunction(array $xv) {
    $max = $this->getArgument('max');

    $yv = array();
    foreach ($xv as $x) {
      if ($x > $max) {
        $yv[] = null;
      } else {
        $yv[] = $x;
      }
    }

    return $yv;
  }

}
