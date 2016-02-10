<?php

final class ConduitBoolParameterType
  extends ConduitListParameterType {

  protected function getParameterValue(array $request, $key) {
    $value = parent::getParameterValue($request, $key);

    if (!is_bool($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected boolean (true or false), got something else.'));
    }

    return $value;
  }

  protected function getParameterTypeName() {
    return 'bool';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A boolean.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'true',
      'false',
    );
  }

}
