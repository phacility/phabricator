<?php

final class PhabricatorChartFunctionArgumentParser
  extends Phobject {

  private $function;
  private $rawArguments;
  private $unconsumedArguments;
  private $haveAllArguments = false;
  private $unparsedArguments;
  private $argumentMap = array();
  private $argumentPosition = 0;
  private $argumentValues = array();

  public function setFunction(PhabricatorChartFunction $function) {
    $this->function = $function;
    return $this;
  }

  public function getFunction() {
    return $this->function;
  }

  public function setRawArguments(array $arguments) {
    $this->rawArguments = $arguments;
    $this->unconsumedArguments = $arguments;
  }

  public function addArgument(PhabricatorChartFunctionArgument $spec) {
    $name = $spec->getName();
    if (!strlen($name)) {
      throw new Exception(
        pht(
          'Chart function "%s" emitted an argument specification with no '.
          'argument name. Argument specifications must have unique names.',
          $this->getFunctionArgumentSignature()));
    }

    $type = $spec->getType();
    if (!strlen($type)) {
      throw new Exception(
        pht(
          'Chart function "%s" emitted an argument specification ("%s") with '.
          'no type. Each argument specification must have a valid type.',
          $name));
    }

    if (isset($this->argumentMap[$name])) {
      throw new Exception(
        pht(
          'Chart function "%s" emitted multiple argument specifications '.
          'with the same name ("%s"). Each argument specification must have '.
          'a unique name.',
          $this->getFunctionArgumentSignature(),
          $name));
    }

    $this->argumentMap[$name] = $spec;
    $this->unparsedArguments[] = $spec;

    return $this;
  }

  public function parseArgument(
    PhabricatorChartFunctionArgument $spec) {
    $this->addArgument($spec);
    return $this->parseArguments();
  }

  public function setHaveAllArguments($have_all) {
    $this->haveAllArguments = $have_all;
    return $this;
  }

  public function parseArguments() {
    $have_count = count($this->rawArguments);
    $want_count = count($this->argumentMap);

    if ($this->haveAllArguments) {
      if ($want_count !== $have_count) {
        throw new Exception(
          pht(
            'Function "%s" expects %s argument(s), but %s argument(s) were '.
            'provided.',
            $this->getFunctionArgumentSignature(),
            $want_count,
            $have_count));
      }
    }

    while ($this->unparsedArguments) {
      $argument = array_shift($this->unparsedArguments);
      $name = $argument->getName();

      if (!$this->unconsumedArguments) {
        throw new Exception(
          pht(
            'Function "%s" expects at least %s argument(s), but only %s '.
            'argument(s) were provided.',
            $this->getFunctionArgumentSignature(),
            $want_count,
            $have_count));
      }

      $raw_argument = array_shift($this->unconsumedArguments);
      $this->argumentPosition++;

      try {
        $value = $argument->newValue($raw_argument);
      } catch (Exception $ex) {
        throw new Exception(
          pht(
            'Argument "%s" (in position "%s") to function "%s" is '.
            'invalid: %s',
            $name,
            $this->argumentPosition,
            $this->getFunctionArgumentSignature(),
            $ex->getMessage()));
      }

      $this->argumentValues[$name] = $value;
    }
  }

  public function getArgumentValue($key) {
    if (!array_key_exists($key, $this->argumentValues)) {
      throw new Exception(
        pht(
          'Function "%s" is requesting an argument ("%s") that it did '.
          'not define.',
          $this->getFunctionArgumentSignature(),
          $key));
    }

    return $this->argumentValues[$key];
  }

  private function getFunctionArgumentSignature() {
    $argument_list = array();
    foreach ($this->argumentMap as $key => $spec) {
      $argument_list[] = $key;
    }

    if (!$this->haveAllArguments) {
      $argument_list[] = '...';
    }

    return sprintf(
      '%s(%s)',
      $this->getFunction()->getFunctionKey(),
      implode(', ', $argument_list));
  }

  public function getSourceFunctionArgument() {
    $required_type = 'function';

    $sources = array();
    foreach ($this->argumentMap as $key => $argument) {
      if (!$argument->getIsSourceFunction()) {
        continue;
      }

      if ($argument->getType() !== $required_type) {
        throw new Exception(
          pht(
            'Function "%s" defines an argument "%s" which is marked as a '.
            'source function, but the type of this argument is not "%s".',
            $this->getFunctionArgumentSignature(),
            $argument->getName(),
            $required_type));
      }

      $sources[$key] = $argument;
    }

    if (!$sources) {
      return null;
    }

    if (count($sources) > 1) {
      throw new Exception(
        pht(
          'Function "%s" defines more than one argument as a source '.
          'function (arguments: %s). Functions must have zero or one '.
          'source function.',
          $this->getFunctionArgumentSignature(),
          implode(', ', array_keys($sources))));
    }

    return head($sources);
  }

}
