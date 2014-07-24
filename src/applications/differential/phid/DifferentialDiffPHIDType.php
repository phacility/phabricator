<?php

final class DifferentialDiffPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'DIFF';

  public function getTypeName() {
    return pht('Differential Diff');
  }

  public function newObject() {
    return new DifferentialDiff();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DifferentialDiffQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $diff = $objects[$phid];

      $id = $diff->getID();

      $handle->setName(pht('Diff %d', $id));
      $handle->setURI("/differential/diff/{$id}/");
    }
  }

}
