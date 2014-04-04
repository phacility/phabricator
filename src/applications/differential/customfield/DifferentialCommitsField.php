<?php

final class DifferentialCommitsField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:commits';
  }

  public function getFieldName() {
    return pht('Commits');
  }

  public function canDisableField() {
    return false;
  }

  public function getFieldDescription() {
    return pht('Shows associated commits.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    return $this->getObject()->getCommitPHIDs();
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

}
