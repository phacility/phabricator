<?php

final class PhabricatorComposeChartFunction
  extends PhabricatorHigherOrderChartFunction {

  const FUNCTIONKEY = 'compose';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('f')
        ->setType('function')
        ->setRepeatable(true),
    );
  }

  public function evaluateFunction(array $xv) {
    $original_positions = array_keys($xv);
    $remaining_positions = $original_positions;
    foreach ($this->getFunctionArguments() as $function) {
      $xv = $function->evaluateFunction($xv);

      // If a function evaluates to "null" at some positions, we want to return
      // "null" at those positions and stop evaluating the function.

      // We also want to pass "evaluateFunction()" a natural list containing
      // only values it should evaluate: keys should not be important and we
      // should not pass "null". This simplifies implementation of functions.

      // To do this, first create a map from original input positions to
      // function return values.
      $xv = array_combine($remaining_positions, $xv);

      // If a function evaluated to "null" at any position where we evaluated
      // it, the result will be "null". We remove the position from the
      // vector so we stop evaluating it.
      foreach ($xv as $x => $y) {
        if ($y !== null) {
          continue;
        }

        unset($xv[$x]);
      }

      // Store the remaining original input positions for the next round, then
      // throw away the array keys so we're passing the next function a natural
      // list with only non-"null" values.
      $remaining_positions = array_keys($xv);
      $xv = array_values($xv);

      // If we have no more inputs to evaluate, we can bail out early rather
      // than passing empty vectors to functions for evaluation.
      if (!$xv) {
        break;
      }
    }


    $yv = array();
    $xv = array_combine($remaining_positions, $xv);
    foreach ($original_positions as $position) {
      if (isset($xv[$position])) {
        $y = $xv[$position];
      } else {
        $y = null;
      }
      $yv[$position] = $y;
    }

    return $yv;
  }

}
