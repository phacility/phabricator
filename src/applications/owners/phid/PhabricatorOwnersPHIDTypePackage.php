<?php

final class PhabricatorOwnersPHIDTypePackage extends PhabricatorPHIDType {

  const TYPECONST = 'OPKG';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Owners Package');
  }

  public function newObject() {
    return new PhabricatorOwnersPackage();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorOwnersPackageQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $package = $objects[$phid];

      $name = $package->getName();
      $id = $package->getID();

      $handle->setName($name);
      $handle->setURI("/owners/package/{$id}/");
    }
  }

}
