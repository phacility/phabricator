<?php

final class PhabricatorPackagesPackagePHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'PPAK';

  public function getTypeName() {
    return pht('Package');
  }

  public function newObject() {
    return new PhabricatorPackagesPackage();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPackagesApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorPackagesPackageQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $package = $objects[$phid];

      $name = $package->getName();
      $uri = $package->getURI();

      $handle
        ->setName($name)
        ->setURI($uri);
    }
  }

}
