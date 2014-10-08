<?php

final class DrydockLeasePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'DRYL';

  public function getTypeName() {
    return pht('Drydock Lease');
  }

  public function newObject() {
    return new DrydockLease();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DrydockLeaseQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $lease = $objects[$phid];
      $id = $lease->getID();

      $handle->setURI("/drydock/lease/{$id}/");
    }
  }

}
