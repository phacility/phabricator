<?php

final class DifferentialArcanistProjectFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function getRequiredHandlePHIDs() {
    $arcanist_phid = $this->getArcanistProjectPHID();
    if (!$arcanist_phid) {
      return array();
    }

    return array($arcanist_phid);
  }

  public function renderLabelForRevisionView() {
    return 'Arcanist Project:';
  }

  public function renderValueForRevisionView() {
    $arcanist_phid = $this->getArcanistProjectPHID();
    if (!$arcanist_phid) {
      return null;
    }

    $handle = $this->getHandle($arcanist_phid);
    return $handle->getName();
  }

  private function getArcanistProjectPHID() {
    $diff = $this->getDiff();
    return $diff->getArcanistProjectPHID();
  }

}
