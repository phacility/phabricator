<?php

abstract class DifferentialStoredCustomField
  extends DifferentialCustomField {

  private $value;

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function shouldUseStorage() {
    return true;
  }

  public function newStorageObject() {
    return new DifferentialCustomFieldStorage();
  }

  protected function newStringIndexStorage() {
    return new DifferentialCustomFieldStringIndex();
  }

  protected function newNumericIndexStorage() {
    return new DifferentialCustomFieldNumericIndex();
  }

  public function getValueForStorage() {
    return $this->value;
  }

  public function setValueFromStorage($value) {
    $this->value = $value;
    return $this;
  }

  public function setValueFromApplicationTransactions($value) {
    $this->setValue($value);
    return $this;
  }

  public function getConduitDictionaryValue() {
    return $this->getValue();
  }

}
