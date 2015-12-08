<?php

final class PhabricatorHandlesEditField
  extends PhabricatorPHIDListEditField {

  protected function newControl() {
    return id(new AphrontFormHandlesControl());
  }

  protected function newHTTPParameterType() {
    return new ManiphestTaskListHTTPParameterType();
  }

}
