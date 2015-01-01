<?php

final class DifferentialDependenciesField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:dependencies';
  }

  public function getFieldName() {
    return pht('Dependencies');
  }

  public function canDisableField() {
    return false;
  }

  public function getFieldDescription() {
    return pht('Lists revisions this one is depended on by.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getObject()->getPHID(),
      DifferentialRevisionDependedOnByRevisionEdgeType::EDGECONST);
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

}
