<?php

final class PhabricatorEditEngineCheckboxesCommentAction
  extends PhabricatorEditEngineCommentAction {

  private $options = array();

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  public function getPHUIXControlType() {
    return 'checkboxes';
  }

  public function getPHUIXControlSpecification() {
    $options = $this->getOptions();

    $labels = array();
    foreach ($options as $key => $option) {
      $labels[$key] = hsprintf('%s', $option);
    }

    return array(
      'value' => $this->getValue(),
      'keys' => array_keys($options),
      'labels' => $labels,
    );
  }

}
