<?php

final class DrydockBlueprintPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'DRYB';

  public function getTypeName() {
    return pht('Blueprint');
  }

  public function newObject() {
    return new DrydockBlueprint();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DrydockBlueprintQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $blueprint = $objects[$phid];
      $id = $blueprint->getID();

      $handle->setURI("/drydock/blueprint/{$id}/");
    }
  }

}
