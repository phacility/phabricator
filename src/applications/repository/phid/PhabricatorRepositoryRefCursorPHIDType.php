<?php

final class PhabricatorRepositoryRefCursorPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'RREF';

  public function getTypeName() {
    return pht('Repository Ref');
  }

  public function getTypeIcon() {
    return 'fa-code-fork';
  }

  public function newObject() {
    return new PhabricatorRepositoryRefCursor();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRepositoryRefCursorQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $ref = $objects[$phid];

      $name = $ref->getRefName();

      $handle->setName($name);
    }
  }

}
