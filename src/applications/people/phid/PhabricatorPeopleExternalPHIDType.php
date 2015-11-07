<?php

final class PhabricatorPeopleExternalPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'XUSR';

  public function getTypeName() {
    return pht('External Account');
  }

  public function newObject() {
    return new PhabricatorExternalAccount();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorExternalAccountQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $account = $objects[$phid];

      $display_name = $account->getDisplayName();
      $handle->setName($display_name);
    }
  }

}
