<?php

final class ConduitIntParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key, $strict) {
    $value = parent::getParameterValue($request, $key, $strict);
    return $this->parseIntValue($request, $key, $value, $strict);
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
