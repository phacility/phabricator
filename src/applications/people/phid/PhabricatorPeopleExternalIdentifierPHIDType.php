<?php

final class PhabricatorPeopleExternalIdentifierPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'XIDT';

  public function getTypeName() {
    return pht('External Account Identifier');
  }

  public function newObject() {
    return new PhabricatorExternalAccountIdentifier();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorExternalAccountIdentifierQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $identifier = $objects[$phid];
    }
  }

}
