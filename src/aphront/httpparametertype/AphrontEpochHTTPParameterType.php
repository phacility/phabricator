<?php

final class AphrontEpochHTTPParameterType
  extends AphrontHTTPParameterType {

  protected function getParameterExists(AphrontRequest $request, $key) {
    return $request->getExists($key) ||
           $request->getExists($key.'_d');
  }

  protected function getParameterValue(AphrontRequest $request, $key) {
    return AphrontFormDateControlValue::newFromRequest($request, $key);
  }

  protected function getParameterTypeName() {
    return 'epoch';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('An epoch timestamp, as an integer.'),
      pht('An absolute date, as a string.'),
      pht('A relative date, as a string.'),
      pht('Separate date and time inputs, as strings.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=1460050737',
      'v=2022-01-01',
      'v=yesterday',
      'v_d=2022-01-01&v_t=12:34',
    );
  }

}
