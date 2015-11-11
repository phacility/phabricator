<?php

final class PhabricatorMetaMTAApplicationEmailPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'APPE';

  public function getTypeName() {
    return pht('Application Email');
  }

  public function getTypeIcon() {
    return 'fa-email bluegrey';
  }

  public function newObject() {
    return new PhabricatorMetaMTAApplicationEmail();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorMetaMTAApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorMetaMTAApplicationEmailQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $email = $objects[$phid];

      $handle->setName($email->getAddress());
      $handle->setFullName($email->getAddress());
    }
  }
}
