<?php

final class AphrontPHIDListHTTPParameterType
  extends AphrontListHTTPParameterType {

  protected function getParameterValue(AphrontRequest $request, $key) {
    $type = new AphrontStringListHTTPParameterType();
    return $this->getValueWithType($type, $request, $key);
  }

  protected function getParameterTypeName() {
    return 'list<phid>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('Comma-separated list of PHIDs.'),
      pht('List of PHIDs, as array.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=PHID-XXXX-1111',
      'v=PHID-XXXX-1111,PHID-XXXX-2222',
      'v[]=PHID-XXXX-1111&v[]=PHID-XXXX-2222',
    );
  }

}
