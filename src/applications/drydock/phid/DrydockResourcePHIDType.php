<?php

final class DrydockResourcePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'DRYR';

  public function getTypeName() {
    return pht('Drydock Resource');
  }

  public function getTypeIcon() {
    return 'fa-map';
  }

  public function newObject() {
    return new DrydockResource();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDrydockApplication';
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

      $handle->setName(
        pht(
          'Resource %d: %s',
          $id,
          $resource->getResourceName()));

      $handle->setURI("/drydock/resource/{$id}/");
    }
  }

}
