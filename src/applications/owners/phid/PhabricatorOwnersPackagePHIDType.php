<?php

final class PhabricatorOwnersPackagePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'OPKG';

  public function getTypeName() {
    return pht('Owners Package');
  }

  public function getTypeIcon() {
    return 'fa-list-alt';
  }

  public function newObject() {
    return new PhabricatorOwnersPackage();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorOwnersApplication';
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
