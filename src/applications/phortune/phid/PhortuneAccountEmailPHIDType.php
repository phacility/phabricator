<?php

final class PhortuneAccountEmailPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'AEML';

  public function getTypeName() {
    return pht('Phortune Account Email');
  }

  public function newObject() {
    return new PhortuneAccountEmail();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhortuneAccountEmailQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $email = $objects[$phid];

      $id = $email->getID();

      $handle->setName($email->getObjectName());
    }
  }

}
