<?php

final class PhortuneAccountPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'ACNT';

  public function getTypeName() {
    return pht('Phortune Account');
  }

  public function newObject() {
    return new PhortuneAccount();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhortuneApplication';
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

      $handle
        ->setName($account->getName())
        ->setURI($account->getURI());
    }
  }

}
