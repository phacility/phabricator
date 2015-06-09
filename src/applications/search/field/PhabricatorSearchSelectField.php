<?php

final class PhabricatorSearchSelectField
  extends PhabricatorSearchField {

  private $options;
  private $default;

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  protected function getDefaultValue() {
    return $this->default;
  }

  public function setDefault($default) {
    $this->default = $default;
    return $this;
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $request->getStr($key);
  }

  protected function newControl() {
    return id(new AphrontFormSelectControl())
      ->setOptions($this->getOptions());
  }

}
