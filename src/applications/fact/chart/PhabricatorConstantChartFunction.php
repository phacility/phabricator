<?php

final class PhabricatorConstantChartFunction
  extends PhabricatorPureChartFunction {

  const FUNCTIONKEY = 'constant';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('n')
        ->setType('number'),
    );
  }

  public function evaluateFunction(array $xv) {
    $n = $this->getArgument('n');

    $yv = array();

    foreach ($xv as $x) {
      $yv[] = $n;
    }

    return $yv;
  }

}
