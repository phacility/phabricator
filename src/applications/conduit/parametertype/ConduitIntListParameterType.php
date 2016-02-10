<?php

final class ConduitIntListParameterType
  extends ConduitListParameterType {

  protected function getParameterValue(array $request, $key) {
    $list = parent::getParameterValue($request, $key);

    foreach ($list as $idx => $item) {
      if (!is_int($item)) {
        $this->raiseValidationException(
          $request,
          $key,
          pht(
            'Expected a list of integers, but item with index "%s" is '.
            'not an integer.',
            $idx));
      }
    }

    return $list;
  }

  protected function getParameterTypeName() {
    return 'list<int>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('List of integers.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      '[123, 0, -456]',
    );
  }

}
