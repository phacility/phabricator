<?php

final class PhabricatorBoolEditField
  extends PhabricatorEditField {

  private $options;
  private $asCheckbox;

  public function setOptions($off_label, $on_label) {
    $this->options = array(
      '0' => $off_label,
      '1' => $on_label,
    );
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  public function setAsCheckbox($as_checkbox) {
    $this->asCheckbox = $as_checkbox;
    return $this;
  }

  public function getAsCheckbox() {
    return $this->asCheckbox;
  }

  protected function newControl() {
    $options = $this->getOptions();

    if (!$options) {
      $options = array(
        '0' => pht('False'),
        '1' => pht('True'),
      );
    }

    if ($this->getAsCheckbox()) {
      $key = $this->getKey();
      $value = $this->getValueForControl();
      $checkbox_key = $this->newHTTPParameterType()
        ->getCheckboxKey($key);
      $id = $this->getControlID();

      $control = id(new AphrontFormCheckboxControl())
        ->setCheckboxKey($checkbox_key)
        ->addCheckbox($key, '1', $options['1'], $value, $id);
    } else {
      $control = id(new AphrontFormSelectControl())
        ->setOptions($options);
    }

    return $control;
  }

  protected function newHTTPParameterType() {
    return new AphrontBoolHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitBoolParameterType();
  }

}
