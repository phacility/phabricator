<?php

final class PhabricatorSearchIntField
  extends PhabricatorSearchField {

  protected function getDefaultValue() {
    return null;
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $request->getInt($key);
  }

  protected function newControl() {
    return new AphrontFormTextControl();
  }

  protected function newConduitParameterType() {
    return new ConduitIntParameterType();
  }

}
