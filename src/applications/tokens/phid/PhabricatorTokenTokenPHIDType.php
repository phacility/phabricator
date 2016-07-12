<?php

final class PhabricatorTokenTokenPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'TOKN';

  public function getTypeName() {
    return pht('Token');
  }

  public function newObject() {
    return new PhabricatorToken();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorTokensApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorTokenQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $token = $objects[$phid];

      $name = $token->getName();

      $handle->setName(pht('%s Token', $name));
    }
  }

}
