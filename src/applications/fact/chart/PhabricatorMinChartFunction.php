<?php

final class PhabricatorMinChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'min';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('x')
        ->setType('function'),
      $this->newArgument()
        ->setName('min')
        ->setType('number'),
    );
  }

  public function getDomain() {
    return $this->getArgument('x')->getDomain();
  }

  public function newInputValues(PhabricatorChartDataQuery $query) {
    return $this->getArgument('x')->newInputValues($query);
  }

  public function evaluateFunction(array $xv) {
    $yv = $this->getArgument('x')->evaluateFunction($xv);
    $min = $this->getArgument('min');

    foreach ($yv as $k => $y) {
      if ($y < $min) {
        $yv[$k] = null;
      }
    }

    return $yv;
  }

}
