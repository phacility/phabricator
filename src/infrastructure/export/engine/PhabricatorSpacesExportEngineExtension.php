<?php

final class PhabricatorSpacesExportEngineExtension
  extends PhabricatorExportEngineExtension {

  const EXTENSIONKEY = 'spaces';

  public function supportsObject($object) {
    $viewer = $this->getViewer();

    if (!PhabricatorSpacesNamespaceQuery::getViewerSpacesExist($viewer)) {
      return false;
    }

    return ($object instanceof PhabricatorSpacesInterface);
  }

  public function newExportFields() {
    return array(
      id(new PhabricatorPHIDExportField())
        ->setKey('spacePHID')
        ->setLabel(pht('Space PHID')),
      id(new PhabricatorStringExportField())
        ->setKey('space')
        ->setLabel(pht('Space')),
    );
  }

  public function newExportData(array $objects) {
    $viewer = $this->getViewer();

    $space_phids = array();
    foreach ($objects as $object) {
      $space_phids[] = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID(
        $object);
    }
    $handles = $viewer->loadHandles($space_phids);

    $map = array();
    foreach ($objects as $object) {
      $space_phid = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID(
        $object);

      $map[] = array(
        'spacePHID' => $space_phid,
        'space' => $handles[$space_phid]->getName(),
      );
    }

    return $map;
  }

}
