<?php

final class BulkSelectParameterType
  extends BulkParameterType {

  public function getOptions() {
    return $this->getField()->getOptions();
  }

  public function getPHUIXControlType() {
    return 'select';
  }

  public function getPHUIXControlSpecification() {
    return array(
      'options' => $this->getOptions(),
      'order' => array_keys($this->getOptions()),
      'value' => null,
    );
  }

}
