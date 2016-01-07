<?php

final class PhabricatorAuthSSHKeyPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'AKEY';

  public function getTypeName() {
    return pht('Public SSH Key');
  }

  public function newObject() {
    return new PhabricatorAuthSSHKey();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorAuthSSHKeyQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {
    foreach ($handles as $phid => $handle) {
      $key = $objects[$phid];
      $handle->setName(pht('SSH Key %d', $key->getID()));
    }
  }

}
