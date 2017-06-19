<?php

final class PhabricatorSearchCheckboxesField
  extends PhabricatorSearchField {

  private $options;

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  protected function getDefaultValue() {
    return array();
  }

  protected function didReadValueFromSavedQuery($value) {
    if (!is_array($value)) {
      return array();
    }

    return $value;
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $this->getListFromRequest($request, $key);
  }

  protected function newControl() {
    $value = array_fuse($this->getValue());

    $control = new AphrontFormCheckboxControl();
    foreach ($this->getOptions() as $key => $option) {
      $control->addCheckbox(
        $this->getKey().'[]',
        $key,
        $option,
        isset($value[$key]));
    }

    return $control;
  }

  protected function newConduitParameterType() {
    return new ConduitStringListParameterType();
  }

}
