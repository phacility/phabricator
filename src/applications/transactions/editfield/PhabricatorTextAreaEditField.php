<?php

final class PhabricatorTextAreaEditField
  extends PhabricatorEditField {

  private $monospaced;
  private $height;
  private $isStringList;

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

  public function setIsStringList($is_string_list) {
    $this->isStringList = $is_string_list;
    return $this;
  }

  public function getIsStringList() {
    return $this->isStringList;
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

  protected function getValueForControl() {
    $value = $this->getValue();
    if ($this->getIsStringList()) {
      return implode("\n", $value);
    } else {
      return $value;
    }
  }

  protected function newConduitParameterType() {
    if ($this->getIsStringList()) {
      return new ConduitStringListParameterType();
    } else {
      return new ConduitStringParameterType();
    }
  }

  protected function newHTTPParameterType() {
    if ($this->getIsStringList()) {
      return new AphrontStringListHTTPParameterType();
    } else {
      return new AphrontStringHTTPParameterType();
    }
  }

}
