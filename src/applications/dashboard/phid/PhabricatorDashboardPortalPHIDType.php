<?php

final class PhabricatorDashboardPortalPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'PRTL';

  public function getTypeName() {
    return pht('Portal');
  }

  public function newObject() {
    return new PhabricatorDashboardPortal();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDashboardApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorDashboardPortalQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $portal = $objects[$phid];

      $handle
        ->setIcon('fa-compass')
        ->setName($portal->getName())
        ->setURI($portal->getURI());
    }
  }

}
