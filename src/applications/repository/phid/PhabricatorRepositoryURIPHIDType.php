<?php

final class PhabricatorRepositoryURIPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'RURI';

  public function getTypeName() {
    return pht('Repository URI');
  }

  public function newObject() {
    return new PhabricatorRepositoryURI();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRepositoryURIQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $uri = $objects[$phid];

      $handle->setName(
        pht('URI %d %s', $uri->getID(), $uri->getDisplayURI()));
    }
  }

}
