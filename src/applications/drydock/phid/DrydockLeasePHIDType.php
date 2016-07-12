<?php

final class DrydockLeasePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'DRYL';

  public function getTypeName() {
    return pht('Drydock Lease');
  }

  public function getTypeIcon() {
    return 'fa-link';
  }

  public function newObject() {
    return new DrydockLease();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDrydockApplication';
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

      $handle->setName(pht(
        'Lease %d',
        $id));
      $handle->setURI("/drydock/lease/{$id}/");
    }
  }

}
