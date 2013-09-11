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

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorOwnersPackageQuery())
      ->setViewer($query->getViewer())
      ->setParentQuery($query)
      ->withPHIDs($phids)
      ->execute();
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
