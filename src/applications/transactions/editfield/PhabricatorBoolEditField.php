<?php

final class PhabricatorBoolEditField
  extends PhabricatorEditField {

  private $options;

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

  protected function newControl() {
    $options = $this->getOptions();

    if (!$options) {
      $options = array(
        '0' => pht('False'),
        '1' => pht('True'),
      );
    }

    return id(new AphrontFormSelectControl())
      ->setOptions($options);
  }

  protected function newHTTPParameterType() {
    return new AphrontBoolHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitBoolParameterType();
  }

}
