<?php

final class PhabricatorSearchStringListField
  extends PhabricatorSearchField {

  private $placeholder;

  public function setPlaceholder($placeholder) {
    $this->placeholder = $placeholder;
    return $this;
  }

  public function getPlaceholder() {
    return $this->placeholder;
  }

  protected function getDefaultValue() {
    return array();
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $request->getStrList($key);
  }

  protected function newControl() {
    $control = new AphrontFormTextControl();

    $placeholder = $this->getPlaceholder();
    if ($placeholder !== null) {
      $control->setPlaceholder($placeholder);
    }

    return $control;
  }

  protected function getValueForControl() {
    return implode(', ', parent::getValueForControl());
  }

  protected function newConduitParameterType() {
    return new ConduitStringListParameterType();
  }

}
