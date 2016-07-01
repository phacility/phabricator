<?php

final class DiffusionCommitRelationshipSource
  extends PhabricatorObjectRelationshipSource {

  public function isEnabledForObject($object) {
    $viewer = $this->getViewer();

    return PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDiffusionApplication',
      $viewer);
  }

  public function getResultPHIDTypes() {
    return array(
      PhabricatorRepositoryCommitPHIDType::TYPECONST,
    );
  }

  public function getFilters() {
    $filters = parent::getFilters();
    unset($filters['assigned']);
    return $filters;
  }

}
