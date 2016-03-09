<?php

final class AlmanacNamespacePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'ANAM';

  public function getTypeName() {
    return pht('Almanac Namespace');
  }

  public function newObject() {
    return new AlmanacNamespace();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new AlmanacNamespaceQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $namespace = $objects[$phid];

      $id = $namespace->getID();
      $name = $namespace->getName();

      $handle->setObjectName(pht('Namespace %d', $id));
      $handle->setName($name);
      $handle->setURI($namespace->getURI());
    }
  }

}
