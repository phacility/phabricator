<?php

final class PhabricatorSearchStringListField
  extends PhabricatorSearchField {

  protected function getDefaultValue() {
    return array();
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $request->getStrList($key);
  }

  protected function newControl() {
    return new AphrontFormTextControl();
  }

  protected function getValueForControl() {
    return implode(', ', parent::getValueForControl());
  }

}
