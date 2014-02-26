<?php

final class DifferentialApplyPatchField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:apply-patch';
  }

  public function getFieldName() {
    return pht('Apply Patch');
  }

  public function getFieldDescription() {
    return pht('Provides instructions for applying a local patch.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function renderPropertyViewValue(array $handles) {
    $mono = $this->getObject()->getMonogram();

    return phutil_tag('tt', array(), "arc patch {$mono}");
  }

}
