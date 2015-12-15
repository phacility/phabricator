<?php

final class ConduitStringListParameterType
  extends ConduitListParameterType {

  protected function getParameterValue(array $request, $key) {
    $list = parent::getParameterValue($request, $key);
    return $this->validateStringList($request, $key, $list);
  }

  protected function getParameterTypeName() {
    return 'list<string>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('List of strings.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '["mango", "nectarine"]',
    );
  }

}
