<?php

final class PhabricatorCosChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'cos';

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
    return cos(deg2rad($x));
  }

}
