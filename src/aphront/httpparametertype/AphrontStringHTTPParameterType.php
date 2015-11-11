<?php

final class AphrontStringHTTPParameterType
  extends AphrontHTTPParameterType {

  protected function getParameterValue(AphrontRequest $request, $key) {
    return $request->getStr($key);
  }

  protected function getParameterTypeName() {
    return 'string';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A URL-encoded string.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=simple',
      'v=properly%20escaped%20text',
    );
  }

}
