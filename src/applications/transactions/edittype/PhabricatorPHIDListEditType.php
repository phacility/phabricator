<?php

abstract class PhabricatorPHIDListEditType
  extends PhabricatorEditType {

  private $datasource;
  private $isSingleValue;
  private $defaultValue;

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

  public function getDefaultValue() {
    return $this->defaultValue;
  }

  public function getValueType() {
    if ($this->getIsSingleValue()) {
      return 'phid';
    } else {
      return 'list<phid>';
    }
  }

  protected function newConduitParameterType() {
    $default = parent::newConduitParameterType();
    if ($default) {
      return $default;
    }

    if ($this->getIsSingleValue()) {
      return new ConduitPHIDParameterType();
    } else {
      return new ConduitPHIDListParameterType();
    }
  }

}
