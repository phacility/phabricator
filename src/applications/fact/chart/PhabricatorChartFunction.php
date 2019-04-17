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

}
