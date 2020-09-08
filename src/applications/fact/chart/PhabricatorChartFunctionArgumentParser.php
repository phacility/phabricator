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
  private $repeatableArgument = null;

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
          $this->getFunctionArgumentSignature(),
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

    if ($this->repeatableArgument) {
      if ($spec->getRepeatable()) {
        throw new Exception(
          pht(
            'Chart function "%s" emitted multiple repeatable argument '.
            'specifications ("%s" and "%s"). Only one argument may be '.
            'repeatable and it must be the last argument.',
            $this->getFunctionArgumentSignature(),
            $name,
            $this->repeatableArgument->getName()));
      } else {
        throw new Exception(
          pht(
            'Chart function "%s" emitted a repeatable argument ("%s"), then '.
            'another argument ("%s"). No arguments are permitted after a '.
            'repeatable argument.',
            $this->getFunctionArgumentSignature(),
            $this->repeatableArgument->getName(),
            $name));
      }
    }

    if ($spec->getRepeatable()) {
      $this->repeatableArgument = $spec;
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

  public function getAllArguments() {
    return array_values($this->argumentMap);
  }

  public function getRawArguments() {
    return $this->rawArguments;
  }

  public function parseArguments() {
    $have_count = count($this->rawArguments);
    $want_count = count($this->argumentMap);

    if ($this->haveAllArguments) {
      if ($this->repeatableArgument) {
        if ($want_count > $have_count) {
          throw new Exception(
            pht(
              'Function "%s" expects %s or more argument(s), but only %s '.
              'argument(s) were provided.',
              $this->getFunctionArgumentSignature(),
              $want_count,
              $have_count));
        }
      } else if ($want_count !== $have_count) {
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

      $is_repeatable = $argument->getRepeatable();

      // If this argument is repeatable and we have more arguments, add it
      // back to the end of the list so we can continue parsing.
      if ($is_repeatable && $this->unconsumedArguments) {
        $this->unparsedArguments[] = $argument;
      }

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

      if ($is_repeatable) {
        if (!isset($this->argumentValues[$name])) {
          $this->argumentValues[$name] = array();
        }
        $this->argumentValues[$name][] = $value;
      } else {
        $this->argumentValues[$name] = $value;
      }
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

    if (!$this->haveAllArguments || $this->repeatableArgument) {
      $argument_list[] = '...';
    }

    return sprintf(
      '%s(%s)',
      $this->getFunction()->getFunctionKey(),
      implode(', ', $argument_list));
  }

}
