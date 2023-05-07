<?php

final class PhabricatorTextEditField
  extends PhabricatorEditField {

  private $placeholder;

  public function setPlaceholder($placeholder) {
    $this->placeholder = $placeholder;
    return $this;
  }

  public function getPlaceholder() {
    return $this->placeholder;
  }

  protected function newControl() {
    $control = new AphrontFormTextControl();

    $placeholder = $this->getPlaceholder();
    if ($placeholder !== null && strlen($placeholder)) {
      $control->setPlaceholder($placeholder);
    }

    return $control;
  }

  protected function newConduitParameterType() {
    return new ConduitStringParameterType();
  }

  protected function newBulkParameterType() {
    return new BulkStringParameterType();
  }

}
