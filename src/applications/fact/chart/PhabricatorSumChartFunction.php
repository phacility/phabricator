<?php

final class PhabricatorSumChartFunction
  extends PhabricatorHigherOrderChartFunction {

  const FUNCTIONKEY = 'sum';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('f')
        ->setType('function')
        ->setRepeatable(true),
    );
  }

  public function evaluateFunction(array $xv) {
    $fv = array();
    foreach ($this->getFunctionArguments() as $function) {
      $fv[] = $function->evaluateFunction($xv);
    }

    $n = count($xv);
    $yv = array_fill(0, $n, null);

    foreach ($fv as $f) {
      for ($ii = 0; $ii < $n; $ii++) {
        if ($f[$ii] !== null) {
          if (!isset($yv[$ii])) {
            $yv[$ii] = 0;
          }
          $yv[$ii] += $f[$ii];
        }
      }
    }

    return $yv;
  }

}
