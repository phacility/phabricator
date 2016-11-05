<?php

final class AphrontBoolHTTPParameterType
  extends AphrontHTTPParameterType {

  protected function getParameterExists(AphrontRequest $request, $key) {
    if ($request->getExists($key)) {
      return true;
    }

    $checkbox_key = $this->getCheckboxKey($key);
    if ($request->getExists($checkbox_key)) {
      return true;
    }

    return false;
  }

  protected function getParameterValue(AphrontRequest $request, $key) {
    return (bool)$request->getBool($key);
  }

  protected function getParameterTypeName() {
    return 'bool';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A boolean value (true or false).'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=true',
      'v=false',
      'v=1',
      'v=0',
    );
  }

  public function getCheckboxKey($key) {
    return "{$key}.exists";
  }

}
