<?php

final class ConduitStringParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key, $strict) {
    $value = parent::getParameterValue($request, $key, $strict);
    return $this->parseStringValue($request, $key, $value, $strict);
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
