<?php

final class PhabricatorSubmitEditField
  extends PhabricatorEditField {

  protected function renderControl() {
    return id(new AphrontFormSubmitControl())
      ->setValue($this->getValue());
  }

  protected function newHTTPParameterType() {
    return null;
  }

  protected function newConduitParameterType() {
    return null;
  }

}
