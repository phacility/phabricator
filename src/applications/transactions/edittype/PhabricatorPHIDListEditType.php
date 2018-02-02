<?php

abstract class PhabricatorPHIDListEditType
  extends PhabricatorEditType {

  private $datasource;
  private $isSingleValue;
  private $defaultValue;
  private $isNullable;

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function getDatasource() {
    return $this->datasource;
  }

  public function setIsSingleValue($is_single_value) {
    $this->isSingleValue = $is_single_value;
    return $this;
  }

  public function getIsSingleValue() {
    return $this->isSingleValue;
  }

  public function setDefaultValue(array $default_value) {
    $this->defaultValue = $default_value;
    return $this;
  }

  public function setIsNullable($is_nullable) {
    $this->isNullable = $is_nullable;
    return $this;
  }

  public function getIsNullable() {
    return $this->isNullable;
  }

  public function getDefaultValue() {
    return $this->defaultValue;
  }

  protected function newConduitParameterType() {
    $default = parent::newConduitParameterType();
    if ($default) {
      return $default;
    }

    if ($this->getIsSingleValue()) {
      return id(new ConduitPHIDParameterType())
        ->setIsNullable($this->getIsNullable());
    } else {
      return new ConduitPHIDListParameterType();
    }
  }

  public function getTransactionValueFromBulkEdit($value) {
    if (!$this->getIsSingleValue()) {
      return $value;
    }

    if ($value) {
      return head($value);
    }

    return null;
  }

}
