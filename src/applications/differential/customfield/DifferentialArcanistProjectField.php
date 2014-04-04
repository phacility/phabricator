<?php

final class DifferentialArcanistProjectField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:arcanist-project';
  }

  public function getFieldName() {
    return pht('Arcanist Project');
  }

  public function getFieldDescription() {
    return pht('Shows arcanist project name.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    $phid = $this->getArcanistProjectPHID();
    if ($phid) {
      return array($phid);
    }
    return array();
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

  private function getArcanistProjectPHID() {
    return $this->getObject()->getActiveDiff()->getArcanistProjectPHID();
  }

}
