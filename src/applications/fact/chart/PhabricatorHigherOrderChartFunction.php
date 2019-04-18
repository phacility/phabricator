<?php

abstract class PhabricatorHigherOrderChartFunction
  extends PhabricatorChartFunction {

  public function getDomain() {
    $minv = array();
    $maxv = array();
    foreach ($this->getFunctionArguments() as $function) {
      $domain = $function->getDomain();
      if ($domain !== null) {
        list($min, $max) = $domain;
        $minv[] = $min;
        $maxv[] = $max;
      }
    }

    if (!$minv && !$maxv) {
      return null;
    }

    $min = null;
    $max = null;

    if ($minv) {
      $min = min($minv);
    }

    if ($maxv) {
      $max = max($maxv);
    }

    return array($min, $max);
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
