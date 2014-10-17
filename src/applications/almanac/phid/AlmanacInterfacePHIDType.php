<?php

final class AlmanacInterfacePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'AINT';

  public function getTypeName() {
    return pht('Almanac Interface');
  }

  public function newObject() {
    return new AlmanacInterface();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new AlmanacInterfaceQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $interface = $objects[$phid];

      $id = $interface->getID();

      $handle->setObjectName(pht('Interface %d', $id));
      $handle->setName(pht('Interface %d', $id));
    }
  }

}
