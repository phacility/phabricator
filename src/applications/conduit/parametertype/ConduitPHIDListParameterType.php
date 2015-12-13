<?php

final class ConduitPHIDListParameterType
  extends ConduitListParameterType {

  protected function getParameterValue(array $request, $key) {
    $list = parent::getParameterValue($request, $key);
    return $this->validateStringList($request, $key, $list);
  }

  protected function getParameterTypeName() {
    return 'list<phid>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('List of PHIDs.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '["PHID-WXYZ-1111", "PHID-WXYZ-2222"]',
    );
  }

}
