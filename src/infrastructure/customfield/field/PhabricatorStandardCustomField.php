<?php

abstract class PhabricatorStandardCustomField
  extends PhabricatorCustomField {

  private $fieldKey;
  private $fieldName;
  private $fieldType;
  private $fieldValue;
  private $fieldDescription;

  public function __construct($key) {
    $this->fieldKey = $key;
  }

  public function setFieldName($name) {
    $this->fieldName = $name;
    return $this;
  }

  public function setFieldType($type) {
    $this->fieldType = $type;
    return $this;
  }

  public function getFieldValue() {
    return $this->fieldValue;
  }

  public function setFieldValue($value) {
    $this->fieldValue = $value;
    return $this;
  }

  public function setFieldDescription($description) {
    $this->fieldDescription = $description;
    return $this;
  }


/* -(  PhabricatorCustomField  )--------------------------------------------- */


  public function getFieldKey() {
    return $this->fieldKey;
  }

  public function getFieldName() {
    return coalesce($this->fieldName, parent::getFieldName());
  }

  public function getFieldDescription() {
    return coalesce($this->fieldDescription, parent::getFieldDescription());
  }

  public function getStorageKey() {
    return $this->getFieldKey();
  }

  public function getValueForStorage() {
    return $this->getFieldValue();
  }

  public function setValueFromStorage($value) {
    return $this->setFieldValue($value);
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

}
