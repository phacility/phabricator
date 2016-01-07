<?php

final class PhabricatorTextAreaEditField
  extends PhabricatorEditField {

  private $monospaced;
  private $height;

  public function setMonospaced($monospaced) {
    $this->monospaced = $monospaced;
    return $this;
  }

  public function getMonospaced() {
    return $this->monospaced;
  }

  public function setHeight($height) {
    $this->height = $height;
    return $this;
  }

  public function getHeight() {
    return $this->height;
  }

  protected function newControl() {
    $control = new AphrontFormTextAreaControl();

    if ($this->getMonospaced()) {
      $control->setCustomClass('PhabricatorMonospaced');
    }

    $height = $this->getHeight();
    if ($height) {
      $control->setHeight($height);
    }

    return $control;
  }

  protected function newConduitParameterType() {
    return new ConduitStringParameterType();
  }

}
