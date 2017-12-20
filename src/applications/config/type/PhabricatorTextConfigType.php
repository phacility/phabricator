<?php

abstract class PhabricatorTextConfigType
  extends PhabricatorConfigType {

  public function isValuePresentInRequest(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {
    $value = parent::readValueFromRequest($option, $request);
    return (bool)strlen($value);
  }

  protected function newCanonicalValue(
    PhabricatorConfigOption $option,
    $value) {
    return (string)$value;
  }

  protected function newHTTPParameterType() {
    return new AphrontStringHTTPParameterType();
  }

  protected function newControl(PhabricatorConfigOption $option) {
    return new AphrontFormTextControl();
  }

}
