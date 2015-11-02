<?php

final class PhragmentSnapshotPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PHRS';

  public function getTypeName() {
    return pht('Snapshot');
  }

  public function newObject() {
    return new PhragmentSnapshot();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhragmentApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhragmentSnapshotQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    $viewer = $query->getViewer();
    foreach ($handles as $phid => $handle) {
      $snapshot = $objects[$phid];

      $handle->setName(pht(
        'Snapshot: %s',
        $snapshot->getName()));
      $handle->setURI($snapshot->getURI());
    }
  }

}
