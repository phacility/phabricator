<?php

abstract class ManiphestTaskRelationship
  extends PhabricatorObjectRelationship {

  public function isEnabledForObject($object) {
    $viewer = $this->getViewer();

    $has_app = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorManiphestApplication',
      $viewer);
    if (!$has_app) {
      return false;
    }

    return ($object instanceof ManiphestTask);
  }

}
