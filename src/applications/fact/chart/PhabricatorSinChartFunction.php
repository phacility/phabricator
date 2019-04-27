<?php

final class PhabricatorSinChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'sin';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('x')
        ->setType('function')
        ->setIsSourceFunction(true),
    );
  }

  protected function canEvaluateFunction() {
    return true;
  }

  protected function evaluateFunction($x) {
    return sin(deg2rad($x));
  }

}
