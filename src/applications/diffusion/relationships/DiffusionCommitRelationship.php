<?php

abstract class DiffusionCommitRelationship
  extends PhabricatorObjectRelationship {

  public function isEnabledForObject($object) {
    $viewer = $this->getViewer();

    $has_app = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDiffusionApplication',
      $viewer);
    if (!$has_app) {
      return false;
    }

    return ($object instanceof PhabricatorRepositoryCommit);
  }

}
