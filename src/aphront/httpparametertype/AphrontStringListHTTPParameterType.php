<?php

final class AphrontStringListHTTPParameterType
  extends AphrontListHTTPParameterType {

  protected function getParameterValue(AphrontRequest $request, $key) {
    $list = $request->getArr($key, null);

    if ($list === null) {
      $list = $request->getStrList($key);
    }

    return $list;
  }

  protected function getParameterDefault() {
    return array();
  }

  protected function getParameterTypeName() {
    return 'list<string>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('Comma-separated list of strings.'),
      pht('List of strings, as array.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=cat,dog,pig',
      'v[]=cat&v[]=dog',
    );
  }

}
