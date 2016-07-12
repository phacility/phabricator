<?php

final class ConduitEpochParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key) {
    $value = parent::getParameterValue($request, $key);

    if (!is_int($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected epoch timestamp as integer, got something else.'));
    }

    if ($value <= 0) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Epoch timestamp must be larger than 0, got %d.', $value));
    }

    return $value;
  }

  protected function getParameterTypeName() {
    return 'epoch';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('Epoch timestamp, as an integer.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '1450019509',
    );
  }

}
