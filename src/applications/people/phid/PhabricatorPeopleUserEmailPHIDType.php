<?php

final class PhabricatorPeopleUserEmailPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'EADR';

  public function getTypeName() {
    return pht('User Email');
  }

  public function newObject() {
    return new PhabricatorUserEmail();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorPeopleUserEmailQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $email = $objects[$phid];
      $handle->setName($email->getAddress());
    }

    return null;
  }

}
