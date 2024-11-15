<?php

final class PhabricatorRoleTriggerRuleRecord
  extends Phobject {

  private $type;
  private $value;

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

}
