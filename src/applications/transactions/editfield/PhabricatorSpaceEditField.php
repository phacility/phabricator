<?php

final class PhabricatorSpaceEditField
  extends PhabricatorEditField {

  protected function newControl() {
    // NOTE: This field doesn't do anything on its own, it just serves as a
    // companion to the associated View Policy field.
    return null;
  }

  protected function newHTTPParameterType() {
    return new AphrontPHIDHTTPParameterType();
  }

}
