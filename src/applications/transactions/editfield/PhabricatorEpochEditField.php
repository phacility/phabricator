<?php

final class PhabricatorEpochEditField
  extends PhabricatorEditField {

  private $allowNull;

  public function setAllowNull($allow_null) {
    $this->allowNull = $allow_null;
    return $this;
  }

  public function getAllowNull() {
    return $this->allowNull;
  }

  protected function newControl() {
    return id(new AphrontFormDateControl())
      ->setAllowNull($this->getAllowNull())
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
