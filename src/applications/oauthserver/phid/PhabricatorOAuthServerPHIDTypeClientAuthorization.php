<?php

final class PhabricatorOAuthServerPHIDTypeClientAuthorization
  extends PhabricatorPHIDType {

  const TYPECONST = 'OASA';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('OAuth Authorization');
  }

  public function newObject() {
    return new PhabricatorOAuthClientAuthorization();
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
