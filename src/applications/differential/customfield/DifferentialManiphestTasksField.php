<?php

final class DifferentialManiphestTasksField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:maniphest-tasks';
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

  public function getRequiredHandlePHIDsForPropertyView() {
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getObject()->getPHID(),
      PhabricatorEdgeConfig::TYPE_DREV_HAS_RELATED_TASK);
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

}
