<?php

final class PhabricatorMaxChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'max';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('x')
        ->setType('function'),
      $this->newArgument()
        ->setName('max')
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
    $max = $this->getArgument('max');

    foreach ($yv as $k => $y) {
      if ($y > $max) {
        $yv[$k] = null;
      }
    }

    return $yv;
  }

}
