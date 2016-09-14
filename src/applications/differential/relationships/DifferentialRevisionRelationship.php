<?php

abstract class DifferentialRevisionRelationship
  extends PhabricatorObjectRelationship {

  public function isEnabledForObject($object) {
    $viewer = $this->getViewer();

    $has_app = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDifferentialApplication',
      $viewer);
    if (!$has_app) {
      return false;
    }

    return ($object instanceof DifferentialRevision);
  }

}
