<?php

final class PhabricatorRepositoryIdentityPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'RIDT';

  public function getTypeName() {
    return pht('Repository Identity');
  }

  public function newObject() {
    return new PhabricatorRepositoryIdentity();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRepositoryIdentityQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    $avatar_uri = celerity_get_resource_uri('/rsrc/image/avatar.png');
    foreach ($handles as $phid => $handle) {
      $identity = $objects[$phid];

      $id = $identity->getID();
      $name = $identity->getIdentityNameRaw();

      $handle->setObjectName(pht('Identity %d', $id));
      $handle->setName($name);
      $handle->setURI($identity->getURI());
      $handle->setIcon('fa-user');
      $handle->setImageURI($avatar_uri);
    }
  }

}
