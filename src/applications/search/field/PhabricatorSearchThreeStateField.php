<?php

final class PhabricatorSearchThreeStateField
  extends PhabricatorSearchField {

  private $options;

  public function setOptions($null, $yes, $no) {
    $this->options = array(
      '' => $null,
      'true' => $yes,
      'false' => $no,
    );
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  protected function getDefaultValue() {
    return null;
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    if (!strlen($request->getStr($key))) {
      return null;
    }
    return $request->getBool($key);
  }

  protected function newControl() {
    return id(new AphrontFormSelectControl())
      ->setOptions($this->getOptions());
  }

  protected function getValueForControl() {
    $value = parent::getValueForControl();
    if ($value === true) {
      return 'true';
    }
    if ($value === false) {
      return 'false';
    }
    return null;
  }

  protected function newConduitParameterType() {
    return new ConduitBoolParameterType();
  }

}
