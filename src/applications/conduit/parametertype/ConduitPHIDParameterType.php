<?php

final class ConduitPHIDParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key, $strict) {
    $value = parent::getParameterValue($request, $key, $strict);

    if (!is_string($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected PHID, got something else.'));
    }

    return $value;
  }

  protected function getParameterTypeName() {
    return 'phid';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A PHID.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '"PHID-WXYZ-1111222233334444"',
    );
  }

}
