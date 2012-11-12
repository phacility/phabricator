<?php

final class PhabricatorFactSimpleSpec extends PhabricatorFactSpec {

  private $type;
  private $name;
  private $unit;

  public function __construct($type) {
    $this->type = $type;
  }

  public function getType() {
    return $this->type;
  }

  public function setUnit($unit) {
    $this->unit = $unit;
    return $this;
  }

  public function getUnit() {
    return $this->unit;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    if ($this->name !== null) {
      return $this->name;
    }
    return parent::getName();
  }

}
