<?php

abstract class PhabricatorHigherOrderChartFunction
  extends PhabricatorChartFunction {

  public function getDomain() {
    $domains = array();
    foreach ($this->getFunctionArguments() as $function) {
      $domains[] = $function->getDomain();
    }

    return PhabricatorChartInterval::newFromIntervalList($domains);
  }

  public function newInputValues(PhabricatorChartDataQuery $query) {
    $map = array();
    foreach ($this->getFunctionArguments() as $function) {
      $xv = $function->newInputValues($query);
      if ($xv !== null) {
        foreach ($xv as $x) {
          $map[$x] = true;
        }
      }
    }

    if (!$map) {
      return null;
    }

    ksort($map);

    return array_keys($map);
  }

}
