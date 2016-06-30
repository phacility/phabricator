<?php

final class PholioMockRelationshipSource
  extends PhabricatorObjectRelationshipSource {

  public function isEnabledForObject($object) {
    $viewer = $this->getViewer();

    return PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorPholioApplication',
      $viewer);
  }

  public function getResultPHIDTypes() {
    return array(
      PholioMockPHIDType::TYPECONST,
    );
  }

  public function getFilters() {
    $filters = parent::getFilters();
    unset($filters['assigned']);
    return $filters;
  }

}
