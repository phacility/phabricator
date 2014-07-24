<?php

final class DrydockResourcePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'DRYR';

  public function getTypeName() {
    return pht('Drydock Resource');
  }

  public function newObject() {
    return new DrydockResource();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DrydockResourceQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $resource = $objects[$phid];
      $id = $resource->getID();

      $handle->setURI("/drydock/resource/{$id}/");
    }
  }

}
