<?php

final class ConduitBoolParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key, $strict) {
    $value = parent::getParameterValue($request, $key, $strict);
    return $this->parseBoolValue($request, $key, $value, $strict);
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
