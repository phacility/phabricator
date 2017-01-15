<?php

final class ConduitStringListParameterType
  extends ConduitListParameterType {

  protected function getParameterValue(array $request, $key, $strict) {
    $list = parent::getParameterValue($request, $key, $strict);
    return $this->parseStringList($request, $key, $list, $strict);
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
