<?php

final class DoorkeeperExternalObjectPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'XOBJ';

  public function getTypeName() {
    return pht('External Object');
  }

  public function newObject() {
    return new DoorkeeperExternalObject();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDoorkeeperApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DoorkeeperExternalObjectQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $xobj = $objects[$phid];

      $uri = $xobj->getObjectURI();
      $name = $xobj->getDisplayName();
      $full_name = $xobj->getDisplayFullName();

      $handle
        ->setURI($uri)
        ->setName($name)
        ->setFullName($full_name);
    }
  }

}
