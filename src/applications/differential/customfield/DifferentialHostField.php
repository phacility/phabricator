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

  public function renderPropertyViewValue(array $handles) {
    return null;
  }

  public function shouldAppearInDiffPropertyView() {
    return true;
  }

  public function renderDiffPropertyViewLabel(DifferentialDiff $diff) {
    return $this->getFieldName();
  }

  public function renderDiffPropertyViewValue(DifferentialDiff $diff) {
    $host = $diff->getSourceMachine();
    if (!$host) {
      return null;
    }

    return $host;
  }

}
