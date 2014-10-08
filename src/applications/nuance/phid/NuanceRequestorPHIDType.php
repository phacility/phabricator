<?php

final class NuanceRequestorPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'NUAR';

  public function getTypeName() {
    return pht('Requestor');
  }

  public function newObject() {
    return new NuanceRequestor();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new NuanceRequestorQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    $viewer = $query->getViewer();
    foreach ($handles as $phid => $handle) {
      $requestor = $objects[$phid];

      $handle->setName($requestor->getBestName());
      $handle->setURI($requestor->getURI());
    }
  }

}
