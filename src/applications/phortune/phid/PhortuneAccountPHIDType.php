<?php

final class PhortuneAccountPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'ACNT';

  public function getTypeName() {
    return pht('Phortune Account');
  }

  public function newObject() {
    return new PhortuneAccount();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhortuneAccountQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $account = $objects[$phid];

      $id = $account->getID();

      $handle->setName($account->getName());
      $handle->setURI("/phortune/{$id}/");
    }
  }

}
