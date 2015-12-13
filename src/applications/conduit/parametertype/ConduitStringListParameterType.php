<?php

final class ConduitStringListParameterType
  extends ConduitListParameterType {

  protected function getParameterValue(array $request, $key) {
    $list = parent::getParameterValue();

    foreach ($list as $idx => $item) {
      if (!is_string($item)) {
        $this->raiseValidationException(
          $request,
          $key,
          pht(
            'Expected a list of strings, but item with index "%s" is '.
            'not a string.',
            $idx));
      }
    }

    return $list;
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
