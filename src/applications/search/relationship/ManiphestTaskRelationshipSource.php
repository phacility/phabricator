<?php

final class ManiphestTaskRelationshipSource
  extends PhabricatorObjectRelationshipSource {

  public function isEnabledForObject($object) {
    $viewer = $this->getViewer();

    return PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorManiphestApplication',
      $viewer);
  }

  public function getResultPHIDTypes() {
    return array(
      ManiphestTaskPHIDType::TYPECONST,
    );
  }

}
