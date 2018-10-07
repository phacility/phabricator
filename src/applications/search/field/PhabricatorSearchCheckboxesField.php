<?php

final class PhabricatorSearchCheckboxesField
  extends PhabricatorSearchField {

  private $options;
  private $deprecatedOptions = array();

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  public function setDeprecatedOptions(array $deprecated_options) {
    $this->deprecatedOptions = $deprecated_options;
    return $this;
  }

  public function getDeprecatedOptions() {
    return $this->deprecatedOptions;
  }

  protected function getDefaultValue() {
    return array();
  }

  protected function didReadValueFromSavedQuery($value) {
    if (!is_array($value)) {
      return array();
    }

    return $this->getCanonicalValue($value);
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    $value = $this->getListFromRequest($request, $key);
    return $this->getCanonicalValue($value);
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

  public function newConduitConstants() {
    $list = array();

    foreach ($this->getOptions() as $key => $option) {
      $list[] = id(new ConduitConstantDescription())
        ->setKey($key)
        ->setValue($option);
    }

    foreach ($this->getDeprecatedOptions() as $key => $value) {
      $list[] = id(new ConduitConstantDescription())
        ->setKey($key)
        ->setIsDeprecated(true)
        ->setValue(pht('Deprecated alias for "%s".', $value));
    }

    return $list;
  }

  private function getCanonicalValue(array $values) {
    // Always map the current normal options to themselves.
    $normal_options = array_fuse(array_keys($this->getOptions()));

    // Map deprecated values to their new values.
    $deprecated_options = $this->getDeprecatedOptions();

    $map = $normal_options + $deprecated_options;
    foreach ($values as $key => $value) {
      $values[$key] = idx($map, $value, $value);
    }

    return $values;
  }

}
