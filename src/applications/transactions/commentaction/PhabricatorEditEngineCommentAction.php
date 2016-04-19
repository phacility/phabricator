<?php

abstract class PhabricatorEditEngineCommentAction extends Phobject {

  private $key;
  private $label;
  private $value;
  private $initialValue;
  private $order;

  abstract public function getPHUIXControlType();
  abstract public function getPHUIXControlSpecification();

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function getOrder() {
    return $this->order;
  }

  public function getSortVector() {
    return id(new PhutilSortVector())
      ->addInt($this->getOrder());
  }

  public function setInitialValue($initial_value) {
    $this->initialValue = $initial_value;
    return $this;
  }

  public function getInitialValue() {
    return $this->initialValue;
  }

}
