<?php

final class PhabricatorPackagesPublisherPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'PPUB';

  public function getTypeName() {
    return pht('Package Publisher');
  }

  public function newObject() {
    return new PhabricatorPackagesPublisher();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPackagesApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorPackagesPublisherQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $publisher = $objects[$phid];

      $name = $publisher->getName();
      $uri = $publisher->getURI();

      $handle
        ->setName($name)
        ->setURI($uri);
    }
  }

}
