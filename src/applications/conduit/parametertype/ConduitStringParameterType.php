<?php

final class ConduitStringParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key) {
    $value = parent::getParameterValue($request, $key);

    if (!is_string($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected string, got something else.'));
    }

    return $value;
  }

  protected function getParameterTypeName() {
    return 'string';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A string.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '"papaya"',
    );
  }

}
