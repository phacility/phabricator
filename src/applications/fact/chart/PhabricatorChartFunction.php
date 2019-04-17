<?php

abstract class PhabricatorChartFunction
  extends Phobject {

  private $xAxis;
  private $yAxis;

  private $argumentParser;
  private $sourceFunction;

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
    $parser = $this->getArgumentParser();
    $parser->setRawArguments($arguments);

    $specs = $this->newArguments();

    if (!is_array($specs)) {
      throw new Exception(
        pht(
          'Expected "newArguments()" in class "%s" to return a list of '.
          'argument specifications, got %s.',
          get_class($this),
          phutil_describe_type($specs)));
    }

    assert_instances_of($specs, 'PhabricatorChartFunctionArgument');

    foreach ($specs as $spec) {
      $parser->addArgument($spec);
    }

    $parser->setHaveAllArguments(true);
    $parser->parseArguments();

    $source_argument = $parser->getSourceFunctionArgument();
    if ($source_argument) {
      $source_function = $this->getArgument($source_argument->getName());
      $this->setSourceFunction($source_function);
    }

    return $this;
  }

  abstract protected function newArguments();

  final protected function newArgument() {
    return new PhabricatorChartFunctionArgument();
  }

  final protected function getArgument($key) {
    return $this->getArgumentParser()->getArgumentValue($key);
  }

  final protected function getArgumentParser() {
    if (!$this->argumentParser) {
      $parser = id(new PhabricatorChartFunctionArgumentParser())
        ->setFunction($this);

      $this->argumentParser = $parser;
    }
    return $this->argumentParser;
  }

  public function loadData() {
    return;
  }

  protected function setSourceFunction(PhabricatorChartFunction $source) {
    $this->sourceFunction = $source;
    return $this;
  }

  protected function getSourceFunction() {
    return $this->sourceFunction;
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

  protected function canEvaluateFunction() {
    return false;
  }

  protected function evaluateFunction($x) {
    throw new PhutilMethodNotImplementedException();
  }

  public function hasDomain() {
    if ($this->canEvaluateFunction()) {
      return false;
    }

    throw new PhutilMethodNotImplementedException();
  }

  public function getDatapoints(PhabricatorChartDataQuery $query) {
    if ($this->canEvaluateFunction()) {
      $points = $this->newSourceDatapoints($query);
      foreach ($points as $key => $point) {
        $y = $point['y'];
        $y = $this->evaluateFunction($y);
        $points[$key]['y'] = $y;
      }

      return $points;
    }

    return $this->newDatapoints($query);
  }

  protected function newDatapoints(PhabricatorChartDataQuery $query) {
    throw new PhutilMethodNotImplementedException();
  }

  protected function newSourceDatapoints(PhabricatorChartDataQuery $query) {
    $source = $this->getSourceFunction();
    if ($source) {
      return $source->getDatapoints($query);
    }

    return $this->newDefaultDatapoints($query);
  }

  protected function newDefaultDatapoints(PhabricatorChartDataQuery $query) {
    $x_min = $query->getMinimumValue();
    $x_max = $query->getMaximumValue();
    $limit = $query->getLimit();

    $points = array();
    $steps = $this->newLinearSteps($x_min, $x_max, $limit);
    foreach ($steps as $step) {
      $points[] = array(
        'x' => $step,
        'y' => $step,
      );
    }

    return $points;
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
