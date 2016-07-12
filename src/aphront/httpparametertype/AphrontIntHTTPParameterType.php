<?php

final class AphrontIntHTTPParameterType
  extends AphrontHTTPParameterType {

  protected function getParameterValue(AphrontRequest $request, $key) {
    return $request->getInt($key);
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
      'v=123',
    );
  }

}
