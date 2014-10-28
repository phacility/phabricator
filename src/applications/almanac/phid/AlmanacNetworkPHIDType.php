<?php

final class AlmanacNetworkPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'ANET';

  public function getTypeName() {
    return pht('Almanac Network');
  }

  public function newObject() {
    return new AlmanacNetwork();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new AlmanacNetworkQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $network = $objects[$phid];

      $id = $network->getID();
      $name = $network->getName();

      $handle->setObjectName(pht('Network %d', $id));
      $handle->setName($name);
      $handle->setURI($network->getURI());
    }
  }

}
