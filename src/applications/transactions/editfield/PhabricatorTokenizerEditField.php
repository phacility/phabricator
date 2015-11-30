<?php

abstract class PhabricatorTokenizerEditField
  extends PhabricatorPHIDListEditField {

  abstract protected function newDatasource();

  protected function newControl() {
    $control = id(new AphrontFormTokenizerControl())
      ->setDatasource($this->newDatasource());

    $initial_value = $this->getInitialValue();
    if ($initial_value !== null) {
      $control->setOriginalValue($initial_value);
    }

    return $control;
  }

  protected function getInitialValueFromSubmit(AphrontRequest $request, $key) {
    return $request->getArr($key.'.original');
  }

}
