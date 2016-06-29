<?php

final class DifferentialRevisionRelationshipSource
  extends PhabricatorObjectRelationshipSource {

  public function isEnabledForObject($object) {
    $viewer = $this->getViewer();

    return PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDifferentialApplication',
      $viewer);
  }

  public function getResultPHIDTypes() {
    return array(
      DifferentialRevisionPHIDType::TYPECONST,
    );
  }

}
