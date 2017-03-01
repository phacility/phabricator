<?php

final class ConduitPointsParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key, $strict) {
    $value = parent::getParameterValue($request, $key, $strict);

    if (($value !== null) && !is_numeric($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected numeric points value, got something else.'));
    }

    if ($value !== null) {
      $value = (double)$value;
      if ($value < 0) {
        $this->raiseValidationException(
          $request,
          $key,
          pht('Point values must be nonnegative.'));
      }
    }

    return $value;
  }

  protected function getParameterTypeName() {
    return 'points';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A nonnegative number, or null.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'null',
      '0',
      '1',
      '15',
      '0.5',
    );
  }

}
