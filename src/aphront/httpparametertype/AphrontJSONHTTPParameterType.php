<?php

final class AphrontJSONHTTPParameterType
  extends AphrontHTTPParameterType {

  protected function getParameterDefault() {
    return array();
  }

  protected function getParameterValue(AphrontRequest $request, $key) {
    $str = $request->getStr($key);
    return phutil_json_decode($str);
  }

  protected function getParameterTypeName() {
    return 'string (json object)';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A JSON-encoded object.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v={...}',
    );
  }

}
