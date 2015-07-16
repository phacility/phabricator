<?php

final class HeraldSelectFieldValue
  extends HeraldFieldValue {

  private $key;
  private $options;
  private $default;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  public function setDefault($default) {
    $this->default = $default;
    return $this;
  }

  public function getDefault() {
    return $this->default;
  }

  public function getFieldValueKey() {
    if ($this->getKey() === null) {
      throw new PhutilInvalidStateException('setKey');
    }
    return 'select.'.$this->getKey();
  }

  public function getControlType() {
    return self::CONTROL_SELECT;
  }

  protected function getControlTemplate() {
    if ($this->getOptions() === null) {
      throw new PhutilInvalidStateException('setOptions');
    }

    return array(
      'options' => $this->getOptions(),
      'default' => $this->getDefault(),
    );
  }

  public function renderFieldValue($value) {
    $options = $this->getOptions();
    return idx($options, $value, $value);
  }

  public function renderEditorValue($value) {
    return $value;
  }

}
