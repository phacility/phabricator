<?php

final class AphrontSelectHTTPParameterType
  extends AphrontHTTPParameterType {

  protected function getParameterValue(AphrontRequest $request, $key) {
    return $request->getStr($key);
  }

  protected function getParameterTypeName() {
    return 'select';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A single value from the allowed set.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=value',
    );
  }

}
