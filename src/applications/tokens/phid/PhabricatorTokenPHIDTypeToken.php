<?php

final class PhabricatorTokenPHIDTypeToken extends PhabricatorPHIDType {

  const TYPECONST = 'TOKN';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Token');
  }

  public function newObject() {
    return new PhabricatorToken();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorTokenQuery())
      ->setViewer($query->getViewer())
      ->setParentQuery($query)
      ->withPHIDs($phids)
      ->execute();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $token = $objects[$phid];

      $name = $token->getName();

      $handle->setName("{$name} Token");
    }
  }

}
