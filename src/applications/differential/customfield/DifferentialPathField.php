<?php

final class DifferentialPathField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:path';
  }

  public function getFieldName() {
    return pht('Path');
  }

  public function getFieldDescription() {
    return pht('Shows the local path where the diff came from.');
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
    $path = $diff->getSourcePath();
    if (!$path) {
      return null;
    }

    return $path;
  }

}
