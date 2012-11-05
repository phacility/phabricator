<?php

final class HeraldObjectTranscript {

  protected $phid;
  protected $type;
  protected $name;
  protected $fields;

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function getPHID() {
    return $this->phid;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setFields(array $fields) {
    $this->fields = $fields;
    return $this;
  }

  public function getFields() {
    return $this->fields;
  }
}
