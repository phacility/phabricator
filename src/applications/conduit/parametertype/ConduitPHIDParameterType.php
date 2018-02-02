<?php

final class ConduitPHIDParameterType
  extends ConduitParameterType {

  private $isNullable;

  public function setIsNullable($is_nullable) {
    $this->isNullable = $is_nullable;
    return $this;
  }

  public function getIsNullable() {
    return $this->isNullable;
  }

  protected function getParameterValue(array $request, $key, $strict) {
    $value = parent::getParameterValue($request, $key, $strict);

    if ($this->getIsNullable()) {
      if ($value === null) {
        return $value;
      }
    }

    if (!is_string($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected PHID, got something else.'));
    }

    return $value;
  }

  protected function getParameterTypeName() {
    if ($this->getIsNullable()) {
      return 'phid|null';
    } else {
      return 'phid';
    }
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A PHID.'),
    );
  }

  protected function getParameterExamples() {
    $examples = array(
      '"PHID-WXYZ-1111222233334444"',
    );

    if ($this->getIsNullable()) {
      $examples[] = 'null';
    }

    return $examples;
  }

}
