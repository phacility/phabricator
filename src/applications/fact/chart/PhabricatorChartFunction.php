<?php

abstract class PhabricatorChartFunction
  extends Phobject {

  private $xAxis;
  private $yAxis;
  private $limit;

  final public function getFunctionKey() {
    return $this->getPhobjectClassConstant('FUNCTIONKEY', 32);
  }

  final public static function getAllFunctions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getFunctionKey')
      ->execute();
  }

  final public function setArguments(array $arguments) {
    $this->newArguments($arguments);
    return $this;
  }

  abstract protected function newArguments(array $arguments);

  public function loadData() {
    return;
  }

  final public function setXAxis(PhabricatorChartAxis $x_axis) {
    $this->xAxis = $x_axis;
    return $this;
  }

  final public function getXAxis() {
    return $this->xAxis;
  }

  final public function setYAxis(PhabricatorChartAxis $y_axis) {
    $this->yAxis = $y_axis;
    return $this;
  }

  final public function getYAxis() {
    return $this->yAxis;
  }

  protected function newLinearSteps($src, $dst, $count) {
    $count = (int)$count;
    $src = (int)$src;
    $dst = (int)$dst;

    if ($count === 0) {
      throw new Exception(
        pht('Can not generate zero linear steps between two values!'));
    }

    if ($src === $dst) {
      return array($src);
    }

    if ($count === 1) {
      return array($src);
    }

    $is_reversed = ($src > $dst);
    if ($is_reversed) {
      $min = (double)$dst;
      $max = (double)$src;
    } else {
      $min = (double)$src;
      $max = (double)$dst;
    }

    $step = (double)($max - $min) / (double)($count - 1);

    $steps = array();
    for ($cursor = $min; $cursor <= $max; $cursor += $step) {
      $x = (int)round($cursor);

      if (isset($steps[$x])) {
        continue;
      }

      $steps[$x] = $x;
    }

    $steps = array_values($steps);

    if ($is_reversed) {
      $steps = array_reverse($steps);
    }

    return $steps;
  }

}
