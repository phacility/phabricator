<?php

final class PhabricatorSelectEditField
  extends PhabricatorEditField {

  private $options;
  private $optionAliases = array();

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

  public function setOptionAliases(array $option_aliases) {
    $this->optionAliases = $option_aliases;
    return $this;
  }

  public function getOptionAliases() {
    return $this->optionAliases;
  }

  protected function getValueForControl() {
    $value = parent::getValueForControl();

    $options = $this->getOptions();
    if (!isset($options[$value])) {
      $aliases = $this->getOptionAliases();
      if (isset($aliases[$value])) {
        $value = $aliases[$value];
      }
    }

    return $value;
  }

  protected function newControl() {
    return id(new AphrontFormSelectControl())
      ->setOptions($this->getOptions());
  }

  protected function newHTTPParameterType() {
    return new AphrontSelectHTTPParameterType();
  }

  protected function newCommentAction() {
    return id(new PhabricatorEditEngineSelectCommentAction())
      ->setOptions($this->getOptions());
  }

  protected function newConduitParameterType() {
    return new ConduitStringParameterType();
  }

}
