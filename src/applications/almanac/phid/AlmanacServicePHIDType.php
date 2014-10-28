<?php

final class AlmanacServicePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'ASRV';

  public function getTypeName() {
    return pht('Almanac Service');
  }

  public function newObject() {
    return new AlmanacService();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new AlmanacServiceQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $service = $objects[$phid];

      $id = $service->getID();
      $name = $service->getName();

      $handle->setObjectName(pht('Service %d', $id));
      $handle->setName($name);
      $handle->setURI($service->getURI());
    }
  }

}
