<?php

final class PhabricatorEditEngineSelectCommentAction
  extends PhabricatorEditEngineCommentAction {

  private $options;

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  public function getPHUIXControlType() {
    return 'select';
  }

  public function getPHUIXControlSpecification() {
    return array(
      'options' => $this->getOptions(),
      'order' => array_keys($this->getOptions()),
      'value' => $this->getValue(),
    );
  }

}
