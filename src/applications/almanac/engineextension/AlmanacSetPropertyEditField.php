<?php

final class AlmanacSetPropertyEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return null;
  }

  protected function newHTTPParameterType() {
    return null;
  }

  protected function newConduitParameterType() {
    return new ConduitWildParameterType();
  }

  protected function newEditType() {
    return new AlmanacSetPropertyEditType();
  }

}
