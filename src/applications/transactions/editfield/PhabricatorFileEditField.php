<?php

final class PhabricatorFileEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return new PHUIFormFileControl();
  }

  protected function newHTTPParameterType() {
    return new AphrontFileHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitPHIDParameterType();
  }

  public function appendToForm(AphrontFormView $form) {
    $form->setEncType('multipart/form-data');
    return parent::appendToForm($form);
  }

}
