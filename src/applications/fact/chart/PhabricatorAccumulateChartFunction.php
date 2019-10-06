<?php

final class PhabricatorAccumulateChartFunction
  extends PhabricatorHigherOrderChartFunction {

  const FUNCTIONKEY = 'accumulate';

  protected function newArguments() {
    return array(
      $this->newArgument()
        ->setName('x')
        ->setType('function'),
    );
  }

  public function evaluateFunction(array $xv) {
    // First, we're going to accumulate the underlying function. Then
    // we'll map the inputs through the accumulation.

    $datasource = $this->getArgument('x');

    // Use an unconstrained query to pull all the data from the underlying
    // source. We need to accumulate data since the beginning of time to
    // figure out the right Y-intercept -- otherwise, we'll always start at
    // "0" wherever our domain begins.
    $empty_query = new PhabricatorChartDataQuery();

    $datasource_xv = $datasource->newInputValues($empty_query);
    if (!$datasource_xv) {
      // When the datasource has no datapoints, we can't evaluate the function
      // anywhere.
      return array_fill(0, count($xv), null);
    }

    $yv = $datasource->evaluateFunction($datasource_xv);

    $map = array_combine($datasource_xv, $yv);

    $accumulator = 0;
    foreach ($map as $x => $y) {
      $accumulator += $y;
      $map[$x] = $accumulator;
    }

    // The value of "accumulate(x)" is the largest datapoint in the map which
    // is no larger than "x".

    $map_x = array_keys($map);
    $idx = -1;
    $max = count($map_x) - 1;

    $yv = array();

    $value = 0;
    foreach ($xv as $x) {
      // While the next "x" we need to evaluate the function at lies to the
      // right of the next datapoint, move the current datapoint forward until
      // we're at the rightmost datapoint which is not larger than "x".
      while ($idx < $max) {
        if ($map_x[$idx + 1] > $x) {
          break;
        }

        $idx++;
        $value = $map[$map_x[$idx]];
      }

      $yv[] = $value;
    }

    return $yv;
  }

}
