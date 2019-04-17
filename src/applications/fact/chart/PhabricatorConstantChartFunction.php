<?php

final class PhabricatorConstantChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'constant';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('n')
        ->setType('number'),
    );
  }

  protected function canEvaluateFunction() {
    return true;
  }

  protected function evaluateFunction($x) {
    return $this->getArgument('n');
  }

}
