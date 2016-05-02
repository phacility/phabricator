<?php


final class PhabricatorEditPage
  extends Phobject {

  private $key;
  private $label;
  private $fieldKeys = array();
  private $viewURI;
  private $isDefault;

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

  public function setFieldKeys(array $field_keys) {
    $this->fieldKeys = $field_keys;
    return $this;
  }

  public function getFieldKeys() {
    return $this->fieldKeys;
  }

  public function setIsDefault($is_default) {
    $this->isDefault = $is_default;
    return $this;
  }

  public function getIsDefault() {
    return $this->isDefault;
  }

  public function setViewURI($view_uri) {
    $this->viewURI = $view_uri;
    return $this;
  }

  public function getViewURI() {
    return $this->viewURI;
  }

}
