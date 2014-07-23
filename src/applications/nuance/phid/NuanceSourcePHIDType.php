<?php

final class NuanceSourcePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'NUAS';

  public function getTypeName() {
    return pht('Source');
  }

  public function newObject() {
    return new NuanceSource();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new NuanceSourceQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    $viewer = $query->getViewer();
    foreach ($handles as $phid => $handle) {
      $source = $objects[$phid];

      $handle->setName($source->getName());
      $handle->setURI($source->getURI());
    }
  }

}
