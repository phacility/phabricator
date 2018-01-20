<?php

final class PhabricatorAuthPasswordPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'APAS';

  public function getTypeName() {
    return pht('Auth Password');
  }

  public function newObject() {
    return new PhabricatorAuthPassword();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {
    return id(new PhabricatorAuthPasswordQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $password = $objects[$phid];
    }
  }

}
