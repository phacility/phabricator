<?php

final class PhabricatorPackagesVersionPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'PVER';

  public function getTypeName() {
    return pht('Version');
  }

  public function newObject() {
    return new PhabricatorPackagesVersion();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPackagesApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorPackagesVersionQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $version = $objects[$phid];

      $name = $version->getName();
      $uri = $version->getURI();

      $handle
        ->setName($name)
        ->setURI($uri);
    }
  }

}
