<?php

final class DifferentialManiphestTasksField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:maniphest-tasks';
  }

  public function canDisableField() {
    return false;
  }

  public function getFieldName() {
    return pht('Maniphest Tasks');
  }

  public function getFieldDescription() {
    return pht('Lists associated tasks.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  protected function readValueFromRevision(DifferentialRevision $revision) {
    if (!$revision->getPHID()) {
      return array();
    }

    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision->getPHID(),
      DifferentialRevisionHasTaskEdgeType::EDGECONST);
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    return $this->getValue();
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

}
