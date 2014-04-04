<?php

final class DifferentialHostField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:host';
  }

  public function getFieldName() {
    return pht('Host');
  }

  public function getFieldDescription() {
    return pht('Shows the local host where the diff came from.');
  }

  public function shouldDisableByDefault() {
    return true;
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function renderPropertyViewValue(array $handles) {
    $host = $this->getObject()->getActiveDiff()->getSourceMachine();
    if (!$host) {
      return null;
    }

    return $host;
  }

}
