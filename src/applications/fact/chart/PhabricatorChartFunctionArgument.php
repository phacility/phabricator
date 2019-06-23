<?php

final class PhabricatorChartFunctionArgument
  extends Phobject {

  private $name;
  private $type;
  private $repeatable;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setRepeatable($repeatable) {
    $this->repeatable = $repeatable;
    return $this;
  }

  public function getRepeatable() {
    return $this->repeatable;
  }

  public function setType($type) {
    $types = array(
      'fact-key' => true,
      'function' => true,
      'number' => true,
      'phid' => true,
    );

    if (!isset($types[$type])) {
      throw new Exception(
        pht(
          'Chart function argument type "%s" is unknown. Valid types '.
          'are: %s.',
          $type,
          implode(', ', array_keys($types))));
    }

    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function newValue($value) {
    switch ($this->getType()) {
      case 'phid':
        // TODO: This could be validated better, but probably should not be
        // a primitive type.
        return $value;
      case 'fact-key':
        if (!is_string($value)) {
          throw new Exception(
            pht(
              'Value for "fact-key" argument must be a string, got %s.',
              phutil_describe_type($value)));
        }

        $facts = PhabricatorFact::getAllFacts();
        $fact = idx($facts, $value);
        if (!$fact) {
          throw new Exception(
            pht(
              'Fact key "%s" is not a known fact key.',
              $value));
        }

        return $fact;
      case 'function':
        // If this is already a function object, just return it.
        if ($value instanceof PhabricatorChartFunction) {
          return $value;
        }

        if (!is_array($value)) {
          throw new Exception(
            pht(
              'Value for "function" argument must be a function definition, '.
              'formatted as a list, like: [fn, arg1, arg, ...]. Actual value '.
              'is %s.',
              phutil_describe_type($value)));
        }

        if (!phutil_is_natural_list($value)) {
          throw new Exception(
            pht(
              'Value for "function" argument must be a natural list, not '.
              'a dictionary. Actual value is "%s".',
              phutil_describe_type($value)));
        }

        if (!$value) {
          throw new Exception(
            pht(
              'Value for "function" argument must be a list with a function '.
              'name; got an empty list.'));
        }

        $function_name = array_shift($value);

        if (!is_string($function_name)) {
          throw new Exception(
            pht(
              'Value for "function" argument must be a natural list '.
              'beginning with a function name as a string. The first list '.
              'item has the wrong type, %s.',
              phutil_describe_type($function_name)));
        }

        $functions = PhabricatorChartFunction::getAllFunctions();
        if (!isset($functions[$function_name])) {
          throw new Exception(
            pht(
              'Function "%s" is unknown. Valid functions are: %s',
              $function_name,
              implode(', ', array_keys($functions))));
        }

        return id(clone $functions[$function_name])
          ->setArguments($value);
      case 'number':
        if (!is_float($value) && !is_int($value)) {
          throw new Exception(
            pht(
              'Value for "number" argument must be an integer or double, '.
              'got %s.',
              phutil_describe_type($value)));
        }

        return $value;
    }

    throw new PhutilMethodNotImplementedException();
  }

}
