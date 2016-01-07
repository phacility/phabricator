<?php

final class AphrontPHIDHTTPParameterType
  extends AphrontHTTPParameterType {

  protected function getParameterValue(AphrontRequest $request, $key) {
    return $request->getStr($key);
  }

  protected function getParameterTypeName() {
    return 'phid';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A single object PHID.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=PHID-XXXX-1111',
    );
  }

}
