<?php

final class PhabricatorConduitEditField
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

}
