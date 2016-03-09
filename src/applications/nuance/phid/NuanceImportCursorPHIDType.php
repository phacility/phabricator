<?php

final class NuanceImportCursorPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'NUAC';

  public function getTypeName() {
    return pht('Import Cursor');
  }

  public function newObject() {
    return new NuanceImportCursorData();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorNuanceApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new NuanceImportCursorDataQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    $viewer = $query->getViewer();
    foreach ($handles as $phid => $handle) {
      $item = $objects[$phid];
    }
  }

}
