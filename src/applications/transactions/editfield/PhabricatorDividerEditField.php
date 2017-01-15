<?php

final class PhabricatorDividerEditField
  extends PhabricatorEditField {

  protected function renderControl() {
    return new AphrontFormDividerControl();
  }

  protected function newHTTPParameterType() {
    return null;
  }

  protected function newConduitParameterType() {
    return null;
  }

}
