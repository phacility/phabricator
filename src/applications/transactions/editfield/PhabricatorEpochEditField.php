<?php

final class PhabricatorEpochEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return id(new AphrontFormDateControl())
      ->setViewer($this->getViewer());
  }

  protected function newHTTPParameterType() {
    return new AphrontEpochHTTPParameterType();
  }

  protected function newConduitParameterType() {
    // TODO: This isn't correct, but we don't have any methods which use this
    // yet.
    return new ConduitIntParameterType();
  }

}
