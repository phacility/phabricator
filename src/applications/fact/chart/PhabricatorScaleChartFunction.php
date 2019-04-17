<?php

final class PhabricatorScaleChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'scale';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('x')
        ->setType('function')
        ->setIsSourceFunction(true),
      $this->newArgument()
        ->setName('scale')
        ->setType('number'),
    );
  }

  protected function canEvaluateFunction() {
    return true;
  }

  protected function evaluateFunction($x) {
    return $x * $this->getArgument('scale');
  }

}
