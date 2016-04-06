<?php

final class PhabricatorColumnsEditField
  extends PhabricatorPHIDListEditField {

  protected function newControl() {
    $control = id(new AphrontFormHandlesControl());
    $control->setIsInvisible(true);

    return $control;
  }

  protected function newHTTPParameterType() {
    return new AphrontPHIDListHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitColumnsParameterType();
  }

}
