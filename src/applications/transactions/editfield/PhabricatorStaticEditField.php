<?php

final class PhabricatorStaticEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return new AphrontFormMarkupControl();
  }

  protected function newHTTPParameterType() {
    return null;
  }

  protected function newConduitParameterType() {
    return null;
  }

}
