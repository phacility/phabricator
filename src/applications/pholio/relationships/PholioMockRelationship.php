<?php

abstract class PholioMockRelationship
  extends PhabricatorObjectRelationship {

  public function isEnabledForObject($object) {
    $viewer = $this->getViewer();

    $has_app = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorPholioApplication',
      $viewer);
    if (!$has_app) {
      return false;
    }

    return ($object instanceof PholioMock);
  }

}
