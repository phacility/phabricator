<?php

final class PhabricatorAuthFactorResult
  extends Phobject {

  private $isValid = false;
  private $hint;
  private $value;

  public function setIsValid($is_valid) {
    $this->isValid = $is_valid;
    return $this;
  }

  public function getIsValid() {
    return $this->isValid;
  }

  public function setHint($hint) {
    $this->hint = $hint;
    return $this;
  }

  public function getHint() {
    return $this->hint;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

}
