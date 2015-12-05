<?php

final class AphrontBoolHTTPParameterType
  extends AphrontHTTPParameterType {

  protected function getParameterValue(AphrontRequest $request, $key) {
    return $request->getBool($key);
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

}
