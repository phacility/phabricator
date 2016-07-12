<?php

final class PhabricatorOAuthServerClientAuthorizationPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'OASA';

  public function getTypeName() {
    return pht('OAuth Authorization');
  }

  public function newObject() {
    return new PhabricatorOAuthClientAuthorization();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorOAuthServerApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorOAuthClientAuthorizationQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $authorization = $objects[$phid];
      $handle->setName(pht('Authorization %d', $authorization->getID()));
    }
  }

}
