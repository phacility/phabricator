<?php

final class PhabricatorHandlesEditField
  extends PhabricatorPHIDListEditField {

  private $handleParameterType;
  private $isInvisible;

  public function setHandleParameterType(AphrontHTTPParameterType $type) {
    $this->handleParameterType = $type;
    return $this;
  }

  public function getHandleParameterType() {
    return $this->handleParameterType;
  }

  public function setIsInvisible($is_invisible) {
    $this->isInvisible = $is_invisible;
    return $this;
  }

  public function getIsInvisible() {
    return $this->isInvisible;
  }

  protected function newControl() {
    $control = id(new AphrontFormHandlesControl());

    if ($this->getIsInvisible()) {
      $control->setIsInvisible(true);
    }

    return $control;
  }

  protected function newHTTPParameterType() {
    $type = $this->getHandleParameterType();

    if ($type) {
      return $type;
    }

    return new AphrontPHIDListHTTPParameterType();
  }

}
