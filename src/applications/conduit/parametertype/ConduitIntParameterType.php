<?php

final class ConduitIntParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key) {
    $value = parent::getParameterValue($request, $key);

    if (!is_int($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected integer, got something else.'));
    }

    return $value;
  }

  protected function getParameterTypeName() {
    return 'int';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('An integer.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '123',
      '0',
      '-345',
    );
  }

}
