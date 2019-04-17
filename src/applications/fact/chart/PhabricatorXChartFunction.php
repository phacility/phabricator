<?php

final class PhabricatorXChartFunction
  extends PhabricatorChartFunction {

  const FUNCTIONKEY = 'x';

  protected function newArguments() {
    return array();
  }

  protected function canEvaluateFunction() {
    return true;
  }

  protected function evaluateFunction($x) {
    return $x;
  }

}
