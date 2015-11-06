<?php

final class PhabricatorRepositoryMirrorPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'RMIR';

  public function getTypeName() {
    return pht('Repository Mirror');
  }

  public function newObject() {
    return new PhabricatorRepositoryMirror();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRepositoryMirrorQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $mirror = $objects[$phid];

      $handle->setName(
        pht('Mirror %d %s', $mirror->getID(), $mirror->getRemoteURI()));
      $handle->setURI('/diffusion/mirror/'.$mirror->getID().'/');
    }
  }

}
