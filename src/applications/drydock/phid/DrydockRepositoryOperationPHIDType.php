<?php

final class DrydockRepositoryOperationPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'DRYO';

  public function getTypeName() {
    return pht('Drydock Repository Operation');
  }

  public function newObject() {
    return new DrydockRepositoryOperation();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DrydockRepositoryOperationQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $operation = $objects[$phid];
      $id = $operation->getID();

      $handle->setName(pht('Drydock Repository Operation %d', $id));
      $handle->setURI("/drydock/operation/{$id}/");
    }
  }

}
