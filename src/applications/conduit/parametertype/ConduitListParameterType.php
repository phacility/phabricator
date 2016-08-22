<?php

abstract class ConduitListParameterType
  extends ConduitParameterType {

  private $allowEmptyList = true;

  public function setAllowEmptyList($allow_empty_list) {
    $this->allowEmptyList = $allow_empty_list;
    return $this;
  }

  public function getAllowEmptyList() {
    return $this->allowEmptyList;
  }

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

    if (!$value && !$this->getAllowEmptyList()) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected a nonempty list, but value is an empty list.'));
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
