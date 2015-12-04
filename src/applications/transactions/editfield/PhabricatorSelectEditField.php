<?php

final class PhabricatorSelectEditField
  extends PhabricatorEditField {

  private $options;
  private $commentActionDefaultValue;

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    if ($this->options === null) {
      throw new PhutilInvalidStateException('setOptions');
    }
    return $this->options;
  }

  public function setCommentActionDefaultValue($default) {
    $this->commentActionDefaultValue = $default;
    return $this;
  }

  public function getCommentActionDefaultValue() {
    return $this->commentActionDefaultValue;
  }

  protected function newControl() {
    return id(new AphrontFormSelectControl())
      ->setOptions($this->getOptions());
  }

  protected function newHTTPParameterType() {
    return new AphrontSelectHTTPParameterType();
  }

  public function getCommentEditTypes() {
    $label = $this->getCommentActionLabel();
    if ($label === null) {
      return array();
    }

    $default_value = $this->getCommentActionDefaultValue();
    if ($default_value === null) {
      $default_value = $this->getValue();
    }

    $edit = $this->getEditType()
      ->setLabel($label)
      ->setPHUIXControlType('select')
      ->setPHUIXControlSpecification(
        array(
          'options' => $this->getOptions(),
          'order' => array_keys($this->getOptions()),
          'value' => $default_value,
        ));

    return array($edit);
  }

}
