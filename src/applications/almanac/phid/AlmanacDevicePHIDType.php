<?php

final class AlmanacDevicePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'ADEV';

  public function getTypeName() {
    return pht('Almanac Device');
  }

  public function newObject() {
    return new AlmanacDevice();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new AlmanacDeviceQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $device = $objects[$phid];

      $id = $device->getID();
      $name = $device->getName();

      $handle->setObjectName(pht('Device %d', $id));
      $handle->setName($name);
      $handle->setURI($device->getURI());
    }
  }

}
