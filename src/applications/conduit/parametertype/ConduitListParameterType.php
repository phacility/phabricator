<?php

abstract class ConduitListParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key) {
    $value = parent::getParameterValue($request, $key);

    if (!is_array($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected a list, but value is not a list.'));
    }

    $actual_keys = array_keys($value);
    if ($value) {
      $natural_keys = range(0, count($value) - 1);
    } else {
      $natural_keys = array();
    }

    if ($actual_keys !== $natural_keys) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected a list, but value is an object.'));
    }

    return $value;
  }

  protected function validateStringList(array $request, $key, array $list) {
    foreach ($list as $idx => $item) {
      if (!is_string($item)) {
        $this->raiseValidationException(
          $request,
          $key,
          pht(
            'Expected a list of strings, but item with index "%s" is '.
            'not a string.',
            $idx));
      }
    }

    return $list;
  }

  protected function getParameterDefault() {
    return array();
  }

}
