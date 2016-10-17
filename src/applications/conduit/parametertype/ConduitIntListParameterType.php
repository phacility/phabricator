<?php

final class ConduitIntListParameterType
  extends ConduitListParameterType {

  protected function getParameterValue(array $request, $key, $strict) {
    $list = parent::getParameterValue($request, $key, $strict);

    foreach ($list as $idx => $item) {
      $list[$idx] = $this->parseIntValue(
        $request,
        $key.'['.$idx.']',
        $item,
        $strict);
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
