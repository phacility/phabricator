<?php

final class ConduitConstantDescription extends Phobject {

  private $key;
  private $value;
  private $isDeprecated;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function setIsDeprecated($is_deprecated) {
    $this->isDeprecated = $is_deprecated;
    return $this;
  }

  public function getIsDeprecated() {
    return $this->isDeprecated;
  }

}
