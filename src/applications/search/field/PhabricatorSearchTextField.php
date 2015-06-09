<?php

final class PhabricatorSearchTextField
  extends PhabricatorSearchField {

  protected function getDefaultValue() {
    return '';
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $request->getStr($key);
  }

  protected function newControl() {
    return new AphrontFormTextControl();
  }

}
