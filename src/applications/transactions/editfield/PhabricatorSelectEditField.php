<?php

final class PhabricatorSelectEditField
  extends PhabricatorEditField {

  private $options;

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
