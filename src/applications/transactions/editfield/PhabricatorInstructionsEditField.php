<?php

final class PhabricatorInstructionsEditField
  extends PhabricatorEditField {

  public function appendToForm(AphrontFormView $form) {
    return $form->appendRemarkupInstructions($this->getValue());
  }

  protected function newHTTPParameterType() {
    return null;
  }

  protected function newConduitParameterType() {
    return null;
  }

}
