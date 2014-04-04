<?php

final class DifferentialAuthorField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:author';
  }

  public function getFieldName() {
    return pht('Author');
  }

  public function getFieldDescription() {
    return pht('Stores the revision author.');
  }

  public function canDisableField() {
    return false;
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    return array($this->getObject()->getAuthorPHID());
  }

  public function renderPropertyViewValue(array $handles) {
    return $handles[$this->getObject()->getAuthorPHID()]->renderLink();
  }

}
