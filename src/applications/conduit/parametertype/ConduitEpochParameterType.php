<?php

final class ConduitEpochParameterType
  extends ConduitListParameterType {

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
    return 'epoch';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('Epoch timestamp, as an integer.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '["PHID-PROJ-1111"]',
      '["backend"]',
      '["PHID-PROJ-2222", "frontend"]',
    );
  }

}
