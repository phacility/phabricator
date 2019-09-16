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

  public function getDataRefs(array $xv) {
    $refs = array();

    foreach ($this->getFunctionArguments() as $function) {
      $function_refs = $function->getDataRefs($xv);

      $function_refs = array_select_keys($function_refs, $xv);
      if (!$function_refs) {
        continue;
      }

      foreach ($function_refs as $x => $ref_list) {
        if (!isset($refs[$x])) {
          $refs[$x] = array();
        }
        foreach ($ref_list as $ref) {
          $refs[$x][] = $ref;
        }
      }
    }

    return $refs;
  }

  public function loadRefs(array $refs) {
    $results = array();

    foreach ($this->getFunctionArguments() as $function) {
      $results += $function->loadRefs($refs);
    }

    return $results;
  }

}
