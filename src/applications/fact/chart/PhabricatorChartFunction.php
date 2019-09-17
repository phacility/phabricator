<?php

abstract class PhabricatorChartFunction
  extends Phobject {

  private $argumentParser;
  private $functionLabel;

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

    return $this;
  }

  public function setFunctionLabel(PhabricatorChartFunctionLabel $label) {
    $this->functionLabel = $label;
    return $this;
  }

  public function getFunctionLabel() {
    if (!$this->functionLabel) {
      $this->functionLabel = id(new PhabricatorChartFunctionLabel())
        ->setName(pht('Unlabeled Function'))
        ->setColor('rgba(255, 0, 0, 1)')
        ->setFillColor('rgba(255, 0, 0, 0.15)');
    }

    return $this->functionLabel;
  }

  final public function getKey() {
    return $this->getFunctionLabel()->getKey();
  }

  final public static function newFromDictionary(array $map) {
    PhutilTypeSpec::checkMap(
      $map,
      array(
        'function' => 'string',
        'arguments' => 'list<wild>',
      ));

    $functions = self::getAllFunctions();

    $function_name = $map['function'];
    if (!isset($functions[$function_name])) {
      throw new Exception(
        pht(
          'Attempting to build function "%s" from dictionary, but that '.
          'function is unknown. Known functions are: %s.',
          $function_name,
          implode(', ', array_keys($functions))));
    }

    $function = id(clone $functions[$function_name])
      ->setArguments($map['arguments']);

    return $function;
  }

  public function getSubfunctions() {
    $result = array();
    $result[] = $this;

    foreach ($this->getFunctionArguments() as $argument) {
      foreach ($argument->getSubfunctions() as $subfunction) {
        $result[] = $subfunction;
      }
    }

    return $result;
  }

  public function getFunctionArguments() {
    $results = array();

    $parser = $this->getArgumentParser();
    foreach ($parser->getAllArguments() as $argument) {
      if ($argument->getType() !== 'function') {
        continue;
      }

      $name = $argument->getName();
      $value = $this->getArgument($name);

      if (!is_array($value)) {
        $results[] = $value;
      } else {
        foreach ($value as $arg_value) {
          $results[] = $arg_value;
        }
      }
    }

    return $results;
  }

  public function newDatapoints(PhabricatorChartDataQuery $query) {
    $xv = $this->newInputValues($query);

    if ($xv === null) {
      $xv = $this->newDefaultInputValues($query);
    }

    $xv = $query->selectInputValues($xv);

    $n = count($xv);
    $yv = $this->evaluateFunction($xv);

    $points = array();
    for ($ii = 0; $ii < $n; $ii++) {
      $y = $yv[$ii];

      if ($y === null) {
        continue;
      }

      $points[] = array(
        'x' => $xv[$ii],
        'y' => $y,
      );
    }

    return $points;
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

  abstract public function evaluateFunction(array $xv);
  abstract public function getDataRefs(array $xv);
  abstract public function loadRefs(array $refs);

  public function getDomain() {
    return null;
  }

  public function newInputValues(PhabricatorChartDataQuery $query) {
    return null;
  }

  public function loadData() {
    return;
  }

  protected function newDefaultInputValues(PhabricatorChartDataQuery $query) {
    $x_min = $query->getMinimumValue();
    $x_max = $query->getMaximumValue();
    $limit = $query->getLimit();

    return $this->newLinearSteps($x_min, $x_max, $limit);
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
