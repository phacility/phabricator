<?php

final class PhabricatorShiftChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'shift';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('x')
        ->setType('function')
        ->setIsSourceFunction(true),
      $this->newArgument()
        ->setName('shift')
        ->setType('number'),
    );
  }

  protected function canEvaluateFunction() {
    return true;
  }

  protected function evaluateFunction($x) {
    return $x * $this->getArgument('shift');
  }

}
