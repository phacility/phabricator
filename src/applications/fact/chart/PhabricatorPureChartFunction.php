<?php

abstract class PhabricatorPureChartFunction
  extends PhabricatorChartFunction {

  public function getDataRefs(array $xv) {
    return array();
  }

  public function loadRefs(array $refs) {
    return array();
  }

}
